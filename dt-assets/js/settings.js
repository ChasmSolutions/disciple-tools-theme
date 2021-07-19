jQuery(document).ready(function() {
  window.current_user_lookup = wpApiSettingsPage.current_user_id
  load_locations()

})

function app_switch (app_key = null) {
  let a = jQuery('#app_link_' + app_key)
  a.empty().html(`<span class="loading-spinner active"></span>`)
  makeRequest('post', 'users/app_switch', { app_key })
    .done(function(data) {
      if ('removed' === data) {
        jQuery('#app_link_' + app_key).empty()
      } else {
        let u = a.data('url-base')
        a.empty().html(
          `<a class="button small"  href="${u}${data}" title="${wpApiSettingsPage.translations.link}"><i class="fi-link"></i></a>
            <button class="button small copy_to_clipboard" data-value="${u}${data}" title="${wpApiSettingsPage.translations.copy}"><i class="fi-page-copy"></i></button>`
        )
        load_user_app_copy_to_clipboard_listener()
      }
    })
    .fail(function (err) {
      console.log("error");
      console.log(err);
      a.empty().html(`error`)
    });
}

function load_user_app_copy_to_clipboard_listener() {
  jQuery('.copy_to_clipboard').on('click', function(){
    let str = jQuery(this).data('value')
    const el = document.createElement('textarea');
    el.value = str;
    el.setAttribute('readonly', '');
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    const selected =
      document.getSelection().rangeCount > 0
        ? document.getSelection().getRangeAt(0)
        : false;
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    if (selected) {
      document.getSelection().removeAllRanges();
      document.getSelection().addRange(selected);
    }
    alert('Copied')
  })
}
load_user_app_copy_to_clipboard_listener()

/**
 * Password reset
 *
 * @param preference_key
 * @param type
 * @returns {*}
 */
function switch_preference (preference_key, type = null) {
    return makeRequest('post', 'users/switch_preference', { preference_key, type})
}

function change_password() {
    let translation = wpApiSettingsPage.translations
    // test matching passwords
    const p1 = jQuery('#password1')
    const p2 = jQuery('#password2')
    const message = jQuery('#password-message')

    message.empty()

    if (p1.val() !== p2.val()) {
        message.append(translation.pass_does_not_match)
        return
    }

    makeRequest('post', 'users/change_password', { password: p1 }).done(data => {
        console.log( data )
        message.html(translation.changed)
    }).fail(handleAjaxError)
}

function load_locations() {
  makeRequest( "GET", `user/my` )
    .done(data=>{


      if ( typeof dtMapbox !== "undefined" ) {
        dtMapbox.post_type = 'user'
        write_results_box()

      } else {
        //locations
        let typeahead = Typeahead['.js-typeahead-location_grid']
        if (typeahead) {
          typeahead.items = [];
          typeahead.comparedItems =[];
          typeahead.label.container.empty();
          typeahead.adjustInputSize()
        }
        if ( typeof data.locations.location_grid !== "undefined" ) {
          data.locations.location_grid.forEach(location => {
            typeahead.addMultiselectItemLayout({ID: location.id.toString(), name: location.label})
          })
        }

      }
    }).catch((e)=>{
    console.log( 'error in locations')
    console.log( e)
  })
}

if ( typeof dtMapbox === "undefined" ) {
  let typeaheadTotals = {}
  if (!window.Typeahead['.js-typeahead-location_grid'] ){
    $.typeahead({
      input: '.js-typeahead-location_grid',
      minLength: 0,
      accent: true,
      searchOnFocus: true,
      maxItem: 20,
      dropdownFilter: [{
        key: 'group',
        value: 'focus',
        template: window.lodash.escape(window.wpApiShare.translations.regions_of_focus),
        all: window.lodash.escape(window.wpApiShare.translations.all_locations),
      }],
      source: {
        focus: {
          display: "name",
          ajax: {
            url: wpApiShare.root + 'dt/v1/mapping_module/search_location_grid_by_name',
            data: {
              s: "{{query}}",
              filter: function () {
                return window.lodash.get(window.Typeahead['.js-typeahead-location_grid'].filters.dropdown, 'value', 'all')
              }
            },
            beforeSend: function (xhr) {
              xhr.setRequestHeader('X-WP-Nonce', wpApiShare.nonce);
            },
            callback: {
              done: function (data) {
                if (typeof typeaheadTotals !== "undefined") {
                  typeaheadTotals.field = data.total
                }
                return data.location_grid
              }
            }
          }
        }
      },
      display: "name",
      templateValue: "{{name}}",
      dynamic: true,
      multiselect: {
        matchOn: ["ID"],
        data: function () {
          return [];
        }, callback: {
          onCancel: function (node, item) {
            delete_location_grid( item.ID)
          }
        }
      },
      callback: {
        onClick: function(node, a, item, event){
          add_location_grid( item.ID)
        },
        onReady(){
          this.filters.dropdown = {key: "group", value: "focus", template: window.lodash.escape(window.wpApiShare.translations.regions_of_focus)}
          this.container
            .removeClass("filter")
            .find("." + this.options.selector.filterButton)
            .html(window.lodash.escape(window.wpApiShare.translations.regions_of_focus));
        },
        onResult: function (node, query, result, resultCount) {
          resultCount = typeaheadTotals.location_grid
          let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
          $('#location_grid-result-container').html(text);
        },
        onHideLayout: function () {
          $('#location_grid-result-container').html("");
        }
      }
    });
  }
}
let add_location_grid = ( value )=>{
  let data =  {
    grid_id: value
  }
  return makeRequest( "POST", `users/user_location`, data )
}
let delete_location_grid = ( value )=>{
  let data =  {
    grid_id: value
  }
  return makeRequest( "DELETE", `users/user_location`, data )
}


let update_user = ( key, value )=>{
  let data =  {
    [key]: value
  }
  return makeRequest( "POST", `user/update`, data , 'dt/v1/' )
}

/**
 * Set availability dates
 */
let dateFields = [ "start_date", "end_date" ]
  dateFields.forEach(key=>{
    let datePicker = $(`#${key}.date-picker`)
    datePicker.datepicker({
      onSelect: function (date) {
        let start_date = $('#start_date').val()
        let end_date = $('#end_date').val()
        if ( start_date && end_date && ( moment(start_date) < moment(end_date) )){
          $('#add_unavailable_dates').removeAttr("disabled");
        } else {
          $('#add_unavailable_dates').attr("disabled", true);
        }
      },
      dateFormat: 'yy-mm-dd',
      changeMonth: true,
      changeYear: true,
      yearRange: "-20:+10",
    })
  })

$('#add_unavailable_dates').on('click', function () {
  let start_date = $('#start_date').val()
  let end_date = $('#end_date').val()
  $('#add_unavailable_dates_spinner').addClass('active')
  update_user( 'add_unavailability', {start_date, end_date}).then((resp)=>{
    $('#add_unavailable_dates_spinner').removeClass('active')
    $('#start_date').val('')
    $('#end_date').val('')
    display_dates_unavailable(resp)
  })
})
let display_dates_unavailable = (list = [], first_run )=>{
  let date_unavailable_table = $('#unavailable-list')
  let rows = ``
  list = window.lodash.orderBy( list, [ "start_date" ], "desc")
  list.forEach(range=>{
    rows += `<tr>
        <td>${window.lodash.escape(range.start_date)}</td>
        <td>${window.lodash.escape(range.end_date)}</td>
        <td>
            <button class="button hollow tiny alert remove_dates_unavailable" data-id="${window.lodash.escape(range.id)}" style="margin-bottom: 0">
            <i class="fi-x"></i> ${window.lodash.escape( wpApiSettingsPage.translations.delete )}</button>
        </td>
      </tr>`
  })
  if ( rows || ( !rows && !first_run ) ){
    date_unavailable_table.html(rows)
  }
}
display_dates_unavailable( wpApiSettingsPage.custom_data.availability, true )
$( document).on( 'click', '.remove_dates_unavailable', function () {
  let id = $(this).data('id');
  update_user( 'remove_unavailability', id).then((resp)=>{
    display_dates_unavailable(resp)
  })
})

let status_buttons = $('.status-button')
let color_workload_buttons = (name) =>{
  status_buttons.css('background-color', "")
  status_buttons.addClass("hollow")
  if ( name ){
    let selected = $(`.status-button[name=${name}]`)
    selected.removeClass("hollow")
    selected.css('background-color', window.lodash.get(wpApiSettingsPage, `workload_status_options.${name}.color`))
    selected.blur()
  }
}
color_workload_buttons(wpApiSettingsPage.workload_status )
status_buttons.on( 'click', function () {
  $("#workload-spinner").addClass("active")
  let name = $(this).attr('name')
  color_workload_buttons(name)
  update_user( 'workload_status', name )
  .then(()=>{
    $("#workload-spinner").removeClass("active")
  }).fail(()=>{
    status_buttons.css('background-color', "")
    $("#workload-spinner").removeClass("active")
    status_buttons.addClass("hollow")
  })
})


$('button.dt_multi_select').on('click',function () {
  let fieldKey = $(this).data("field-key")
  let optionKey = $(this).attr('id')
  $(`#${fieldKey}-spinner`).addClass("active")
  let field = jQuery(`[data-field-key="${fieldKey}"]#${optionKey}`)
  field.addClass("submitting-select-button")
  let action = "add"
  let update_request = null
  if (field.hasClass("selected-select-button")){
    action = "delete"
    update_request = update_user( 'remove_' + fieldKey, optionKey )
  } else {
    field.removeClass("empty-select-button")
    field.addClass("selected-select-button")
    update_request = update_user( 'add_' + fieldKey, optionKey )
  }
  update_request.then(()=>{
    field.removeClass("submitting-select-button selected-select-button")
    field.blur();
    field.addClass( action === "delete" ? "empty-select-button" : "selected-select-button");
    $(`#${fieldKey}-spinner`).removeClass("active")
  }).catch(err=>{
    field.removeClass("submitting-select-button selected-select-button")
    field.addClass( action === "add" ? "empty-select-button" : "selected-select-button")
    handleAjaxError(err)
  })
})
$('select.select-field').change(e => {
  const id = $(e.currentTarget).attr('id')
  const val = $(e.currentTarget).val()
  $(`#${id}-spinner`).addClass("active")
  update_user(id, val).then(()=>{
    $(`#${id}-spinner`).removeClass("active")
  }).catch(handleAjaxError)
})
$('input[name="email-preference"]').on('change', (e) => {
  const optionId = e.target.id.replace('-preference', '')
  const loadingSpinner = $('#email-preference-spinner')
  loadingSpinner.addClass('active')
  update_user('email-preference', optionId)
  .then(() => {
    loadingSpinner.removeClass('active')
  })
  .fail(() => {
    loadingSpinner.removeClass('active')
  })
})

/**
 * People groups
 */
$.typeahead({
  input: '.js-typeahead-people_groups',
  minLength: 0,
  accent: true,
  searchOnFocus: true,
  maxItem: 20,
  template: window.TYPEAHEADS.contactListRowTemplate,
  source: TYPEAHEADS.typeaheadPostsSource("peoplegroups" ),
  display: ["name", "label"],
  templateValue: function() {
    if (this.items[this.items.length - 1].label) {
      return "{{label}}"
    } else {
      return "{{name}}"
    }
  },
  dynamic: true,
  multiselect: {
    matchOn: ["ID"],
    data: function () {
      return wpApiSettingsPage.user_people_groups.map(g=>{
        return { ID: g.ID, name:g.post_title };
      })
    },
    callback: {
      onCancel: function (node, item) {
       update_user( 'remove_people_groups', item.ID )
      }
    },
  },
  callback: {
    onClick: function(node, a, item, event){
      update_user( 'add_people_groups', item.ID )
      this.addMultiselectItemLayout(item)
      event.preventDefault()
      this.hideLayout();
      this.resetInput();
    },
    onResult: function (node, query, result, resultCount) {
      let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
      $('#people_groups-result-container').html(text);
    },
    onHideLayout: function () {
      $('#people_groups-result-container').html("");
    }
  }
})
