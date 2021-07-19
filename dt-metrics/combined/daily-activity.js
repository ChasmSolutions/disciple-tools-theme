jQuery(document).ready(function ($) {

  if (window.wpApiShare.url_path.startsWith('metrics/combined/daily-activity')) {
    display_daily_activity()
  }

  function display_daily_activity() {
    jQuery('#metrics-sidemenu').foundation('down', jQuery('#contacts-menu'));

    let chartDiv = jQuery('#chart');

    chartDiv.empty().html(`
    <div class="section-header">${window.lodash.escape(window.wp_js_object.translations.activities)}</div>
    <div class="section-subheader">${window.lodash.escape(window.wp_js_object.translations.filter_to_date_range)}:</div>
    <br>
    <select id="activity_date_range_filter">
        <option selected value="this-month">This Month</option>
        <option value="last-month">Last Month</option>
        <option value="2-months-ago">2 Months Ago</option>
        <option value="3-months-ago">3 Months Ago</option>
        <option value="4-months-ago">4 Months Ago</option>
        <option value="5-months-ago">5 Months Ago</option>
        <option value="6-months-ago">6 Months Ago</option>
    </select>
    <div style="display: inline-block" class="loading-spinner"></div>
    Click on chart timeline day summary to display a detailed list of metrics below.
    <div id="chartdiv" style="height: 600px;"></div><br>
    <h2 id="chart_day_title"></h2><hr>
    <div id="chart_day_counts_div" style="display: none;"></div><br>
    <div id="chart_day_health_counts_div" style="display: none;"></div>`);

    let start_date = window.wp_js_object.data.activities.start;
    let end_date = window.wp_js_object.data.activities.end;
    let days = window.wp_js_object.data.activities.days;

    display_daily_activity_chart(start_date, end_date, days);
  }

  function display_daily_activity_chart(start_date, end_date, days) {
    // Ensure overwritten charts are automatically disposed.
    am4core.options.autoDispose = true;

    // Ensure to hide daily sub-charts.
    $('#chart_day_title').html('');
    $('#chart_day_counts_div').fadeOut('fast', function () {
    });
    $('#chart_day_health_counts_div').fadeOut('fast', function () {
    });

    // Proceed with chart creation.
    $('#chartdiv').fadeOut('fast', function () {
      am4core.ready(function () {

        am4core.useTheme(am4themes_animated);
        am4core.ready(function () {
          let chart = am4core.create("chartdiv", am4plugins_timeline.CurveChart);

          chart.curveContainer.padding(0, 300, 0, 0);
          chart.maskBullets = false;

          let colorSet = new am4core.ColorSet();

          chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";
          chart.dateFormatter.dateFormat = "yyyy-MM-dd";
          chart.fontSize = 10;
          chart.tooltipContainer.fontSize = 10;
          chart.data = build_days_data_array(days, colorSet);
          chart.clickable = true;

          let categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
          categoryAxis.dataFields.category = "category";
          categoryAxis.renderer.grid.template.disabled = true;
          categoryAxis.renderer.labels.template.paddingRight = 25;
          categoryAxis.renderer.minGridDistance = 10;
          categoryAxis.renderer.innerRadius = 10;
          categoryAxis.renderer.radius = 30;

          let dateAxis = chart.xAxes.push(new am4charts.DateAxis());

          dateAxis.renderer.points = getPoints();

          dateAxis.renderer.autoScale = false;
          dateAxis.renderer.autoCenter = false;
          dateAxis.renderer.minGridDistance = 70;
          dateAxis.baseInterval = {count: 1, timeUnit: "day"};
          dateAxis.renderer.tooltipLocation = 0;
          dateAxis.renderer.line.strokeDasharray = "1,4";
          dateAxis.renderer.line.strokeOpacity = 0.5;
          dateAxis.tooltip.background.fillOpacity = 0.2;
          dateAxis.tooltip.background.cornerRadius = 5;
          dateAxis.tooltip.label.fill = new am4core.InterfaceColorSet().getFor("alternativeBackground");
          dateAxis.tooltip.label.paddingTop = 7;
          dateAxis.endLocation = 0;
          dateAxis.startLocation = -0.5;
          dateAxis.min = Date.parse(start_date);
          dateAxis.max = Date.parse(end_date);

          let labelTemplate = dateAxis.renderer.labels.template;
          labelTemplate.verticalCenter = "middle";
          labelTemplate.fillOpacity = 0.6;
          labelTemplate.background.fill = new am4core.InterfaceColorSet().getFor("background");
          labelTemplate.background.fillOpacity = 1;
          labelTemplate.fill = new am4core.InterfaceColorSet().getFor("text");
          labelTemplate.padding(7, 7, 7, 7);

          let series = chart.series.push(new am4plugins_timeline.CurveColumnSeries());
          series.columns.template.height = am4core.percent(30);

          series.dataFields.openDateX = "start";
          series.dataFields.dateX = "end";
          series.dataFields.categoryY = "category";
          series.baseAxis = categoryAxis;
          series.columns.template.propertyFields.fill = "color"; // get color from data
          series.columns.template.propertyFields.stroke = "color";
          series.columns.template.strokeOpacity = 0;
          series.columns.template.fillOpacity = 0.6;

          let imageBullet1 = series.bullets.push(new am4plugins_bullets.PinBullet());
          imageBullet1.background.radius = 0;
          imageBullet1.locationX = 1;
          imageBullet1.propertyFields.stroke = "color";
          imageBullet1.background.propertyFields.fill = "color";
          //..imageBullet1.image = new am4core.Image();
          //..imageBullet1.image.propertyFields.href = "icon";
          //..imageBullet1.image.scale = 0.7;
          //..imageBullet1.circle.radius = am4core.percent(100);
          imageBullet1.background.fillOpacity = 0.8;
          imageBullet1.background.strokeOpacity = 0;
          imageBullet1.dy = -2;
          imageBullet1.background.pointerBaseWidth = 10;
          imageBullet1.background.pointerLength = 10
          imageBullet1.background.hide();
          imageBullet1.background.disabled = true;
          //..imageBullet1.tooltipHTML = "{tooltip}";
          imageBullet1.label = new am4core.Label();
          imageBullet1.label.html = "{tooltip}";

          // Capture bullet clicks and display count breakdowns accordingly!
          imageBullet1.cursorOverStyle = am4core.MouseCursorStyle.pointer;
          imageBullet1.cursorDownStyle = am4core.MouseCursorStyle.grabbing;
          imageBullet1.clickable = true;
          imageBullet1.events.on("hit", function (ev) {
            display_daily_counts(ev.target.dataItem.dataContext);
          });

          series.tooltip.pointerOrientation = "up";

          imageBullet1.background.adapter.add("pointerAngle", (value, target) => {
            if (target.dataItem) {
              var position = dateAxis.valueToPosition(target.dataItem.openDateX.getTime());
              return dateAxis.renderer.positionToAngle(position);
            }
            return value;
          });

          let hs = imageBullet1.states.create("hover");
          hs.properties.scale = 1.3;
          hs.properties.opacity = 1;

          let textBullet = series.bullets.push(new am4charts.LabelBullet());
          textBullet.label.propertyFields.text = "text";
          textBullet.disabled = true;
          textBullet.propertyFields.disabled = "textDisabled";
          textBullet.label.strokeOpacity = 0;
          textBullet.locationX = 1;
          textBullet.dy = -100;
          textBullet.label.textAlign = "middle";

          chart.scrollbarX = new am4core.Scrollbar();
          chart.scrollbarX.align = "center"
          chart.scrollbarX.width = am4core.percent(75);
          chart.scrollbarX.parent = chart.curveContainer;
          chart.scrollbarX.height = 300;
          chart.scrollbarX.orientation = "vertical";
          chart.scrollbarX.x = 128;
          chart.scrollbarX.y = -140;
          chart.scrollbarX.isMeasured = false;
          chart.scrollbarX.opacity = 0.5;

          let cursor = new am4plugins_timeline.CurveCursor();
          chart.cursor = cursor;
          cursor.xAxis = dateAxis;
          cursor.yAxis = categoryAxis;
          cursor.lineY.disabled = true;
          cursor.lineX.disabled = true;

          dateAxis.renderer.tooltipLocation2 = 0;
          categoryAxis.cursorTooltipEnabled = false;

          chart.zoomOutButton.disabled = true;

          let previousBullet;

          chart.events.on("inited", function () {
            setTimeout(function () {
              // DISABLE ROLLOVER FUNCTION // hoverItem(series.dataItems.getIndex(0));
            }, 2000)
          })

          function hoverItem(dataItem) {
            let bullet = dataItem.bullets.getKey(imageBullet1.uid);
            let index = dataItem.index;

            if (index >= series.dataItems.length - 1) {
              index = -1;
            }

            if (bullet) {

              if (previousBullet) {
                previousBullet.isHover = false;
              }

              bullet.isHover = true;

              previousBullet = bullet;
            }
            setTimeout(
              function () {
                hoverItem(series.dataItems.getIndex(index + 1))
              }, 1000);
          }

          // Respond to filter date range changes
          $(document).off('change').on('change', '#activity_date_range_filter', function () {
            refresh_daily_activity_chart(chart, $('#activity_date_range_filter').val());
          });

          // Display Updated Chart
          $('#chartdiv').fadeIn('slow', function () {
          });

        });

        function getPoints() {

          let points = [{x: -1300, y: 200}, {x: 0, y: 200}];

          let w = 400;
          let h = 400;
          let levelCount = 4;

          let radius = am4core.math.min(w / (levelCount - 1) / 2, h / 2);
          let startX = radius;

          for (let i = 0; i < 25; i++) {
            let angle = 0 + i / 25 * 90;
            let centerPoint = {y: 200 - radius, x: 0}
            points.push({
              y: centerPoint.y + radius * am4core.math.cos(angle),
              x: centerPoint.x + radius * am4core.math.sin(angle)
            });
          }


          for (let i = 0; i < levelCount; i++) {

            if (i % 2 != 0) {
              points.push({y: -h / 2 + radius, x: startX + w / (levelCount - 1) * i})
              points.push({y: h / 2 - radius, x: startX + w / (levelCount - 1) * i})

              let centerPoint = {y: h / 2 - radius, x: startX + w / (levelCount - 1) * (i + 0.5)}
              if (i < levelCount - 1) {
                for (let k = 0; k < 50; k++) {
                  let angle = -90 + k / 50 * 180;
                  points.push({
                    y: centerPoint.y + radius * am4core.math.cos(angle),
                    x: centerPoint.x + radius * am4core.math.sin(angle)
                  });
                }
              }

              if (i == levelCount - 1) {
                points.pop();
                points.push({y: -radius, x: startX + w / (levelCount - 1) * i})
                let centerPoint = {y: -radius, x: startX + w / (levelCount - 1) * (i + 0.5)}
                for (let k = 0; k < 25; k++) {
                  let angle = -90 + k / 25 * 90;
                  points.push({
                    y: centerPoint.y + radius * am4core.math.cos(angle),
                    x: centerPoint.x + radius * am4core.math.sin(angle)
                  });
                }
                points.push({y: 0, x: 1300});
              }

            } else {
              points.push({y: h / 2 - radius, x: startX + w / (levelCount - 1) * i})
              points.push({y: -h / 2 + radius, x: startX + w / (levelCount - 1) * i})
              let centerPoint = {y: -h / 2 + radius, x: startX + w / (levelCount - 1) * (i + 0.5)}
              if (i < levelCount - 1) {
                for (let k = 0; k < 50; k++) {
                  let angle = -90 - k / 50 * 180;
                  points.push({
                    y: centerPoint.y + radius * am4core.math.cos(angle),
                    x: centerPoint.x + radius * am4core.math.sin(angle)
                  });
                }
              }
            }
          }

          return points;
        }

      });
    });
  }

  function display_daily_counts(dataContext) {
    $('#chart_day_title').html(dataContext.text);
    display_day_counts(dataContext.counts);
  }

  function display_day_counts(day_counts) {
    //console.log(day_counts);
    let chart_day_counts_div = $('#chart_day_counts_div');
    chart_day_counts_div.fadeOut('fast', function () {

      let metrics_html = '';

      // Default Metrics
      if (parseInt(day_counts['new_contacts']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>New Contacts</td>';
        metrics_html += '<td>' + day_counts['new_contacts'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(day_counts['new_groups']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>New Groups</td>';
        metrics_html += '<td>' + day_counts['new_groups'] + '</td>';
        metrics_html += '</tr>';
      }

      // Seeker Path Updates
      let seeker_path_updates = day_counts['seeker_path_updates'];

      if (parseInt(seeker_path_updates['attempted']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['attempted']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['attempted']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['coaching']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['coaching']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['coaching']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['established']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['established']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['established']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['met']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['met']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['met']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['none']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['none']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['none']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['ongoing']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['ongoing']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['ongoing']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      if (parseInt(seeker_path_updates['scheduled']['value']) > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td>' + seeker_path_updates['scheduled']['label'] + '</td>';
        metrics_html += '<td>' + seeker_path_updates['scheduled']['value'] + '</td>';
        metrics_html += '</tr>';
      }

      // Health
      let health = day_counts['health'];

      if (health && health.length > 0) {
        metrics_html += '<tr>';
        metrics_html += '<td colspan="2" style="background-color:#E8E8E8FF;">Health</td>';
        metrics_html += '</tr>';

        // Iterate over each field option
        health.forEach(function (metric) {
          if (parseInt(metric['practicing']) > 0) {
            metrics_html += '<tr>';
            metrics_html += '<td style="padding-left: 50px;"><li>' + metric['label'] + '</li></td>';
            metrics_html += '<td>' + metric['practicing'] + '</td>';
            metrics_html += '</tr>';
          }
        });
      }

      // Multiselect Fields
      let multiselect_fields = day_counts['multiselect_fields'];
      for (let [field_key, field_value] of Object.entries(multiselect_fields)) {
        metrics_html += '<tr>';
        metrics_html += '<td colspan="2" style="background-color:#E8E8E8FF;">' + field_key + '</td>';
        metrics_html += '</tr>';

        // Iterate over each field option
        field_value.forEach(function (metric) {
          if (parseInt(metric['value']) > 0) {
            metrics_html += '<tr>';
            metrics_html += '<td style="padding-left: 50px;"><li>' + metric['label'] + '</li></td>';
            metrics_html += '<td>' + metric['value'] + '</td>';
            metrics_html += '</tr>';
          }
        });
      }

      // Wrap it all up, with a bow! ;)
      let html = '';
      if (metrics_html) {
        html += '<table>';

        html += '<thead>';
        html += '<tr>';
        html += '<th>Metric</th>';
        html += '<th>Count</th>';
        html += '</tr>';
        html += '</thead>';

        html += '<tbody>';
        html += metrics_html;
        html += '</tbody>';
        html += '</table>';

      } else {
        html += 'No Activity Detected!'
      }

      chart_day_counts_div.html(html);

      // Display Counts Chart
      chart_day_counts_div.fadeIn('slow', function () {
      });
    });
  }

  function refresh_daily_activity_chart(chart, date_range_filter) {

    // Start loading spinner
    $(".loading-spinner").addClass("active");

    jQuery
      .ajax({
        type: "GET",
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        url: `${wp_js_object.rest_endpoints_base}/daily-activity/?date_range=${date_range_filter}`,
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", window.wpApiShare.nonce);
        },
      })
      .done(function (data) {
        // Disable loading spinner
        $(".loading-spinner").removeClass("active");

        console.log(data);
        chart.dispose(); // Force chart disposal
        display_daily_activity_chart(data.start, data.end, data.days);
      })
      .fail(function (err) {
        console.log("error");
        console.log(err);
      });
  }

  function build_days_data_array(days, colorSet) {
    let data = [];

    for (let [key, day] of Object.entries(days)) {
      //console.log(day);

      data.push({
        "category": "",
        "start": key + " 00:00",
        "end": key + " 23:59",
        "color": colorSet.next(),
        "tooltip": fetch_tooltip(key, day),
        "text": key,
        "counts": day
      });
    }

    return data;
  }

  function fetch_tooltip(date, counts) {
    let html = '<h3>' + date + '</h3>';
    html += '<ul>';
    html += '<li>New Contacts: ' + counts['new_contacts'] + '</li>'
    html += '<li>New Groups: ' + counts['new_groups'] + '</li>'
    html += '</ul>';

    return html;
  }
});
