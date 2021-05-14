/**
 * Setup the feature tour using Shepherd
 * https://shepherdjs.dev/docs
 */

const escapeObject = window.SHAREDFUNCTIONS.escapeObject

const {
    done: doneLabel,
    next: nextLabel,
    back: backLabel,
    close_tour: closeTourLabel,
} = escapeObject(window.wpApiShare.translations)

startListTour()

function startListTour() {
    const tourId = 'list_tour'

    const {
        create_post_tour,
        filter_posts_tour,
        view_posts_tour,
    } = escapeObject(window.list_settings.translations)

    if ( isTourCompleted(tourId) ) {
        return
    }

    const tour = createTour()

    createStep(tour, '.create-post-desktop', 'bottom', {
        id: 'create-contact',
        text: create_post_tour,
    }, {
        firstStep: true,
    });

    createStep(tour, '.filter-posts-desktop', 'bottom', {
        id: 'filter-contacts',
        text: filter_posts_tour,
    });

    createStep(tour, '#records-table', 'top-start', {
        id: 'view-contacts',
        text: view_posts_tour,
    }, {
        lastStep: true,
    });

    Shepherd.once('complete', setTourCompleted(tourId))

    tour.start()
}

function isTourCompleted(id) {
    return window.wpApiShare.completed_tours.includes(id)
}

function setTourCompleted(id) {
    makeRequest('POST', `users/disable_product_tour/`, { tour_id: id })
}

function createTour() {
    return new Shepherd.Tour({
        defaultStepOptions: {
            classes: 'shadow-md bg-purple-dark',
            //        scrollTo: true
        },
        useModalOverlay: true,
    });
}

function createStep(tour, selector, position, shepherdOptions = {}, options = {}) {
    const defaultOptions = {
        firstStep: false,
        lastStep: false,
    }

    const opts = { ...defaultOptions, ...options }

    tour.addStep({
        attachTo: {
            element: selector,
            on: position,
        },
        cancelIcon: {
            enabled: true,
            label: closeTourLabel,
        },
        beforeShowPromise: function () {
            return new Promise(function (resolve) {
                const element = document.querySelector(selector)
                if (!element) {
                    return
                }
                resolve()
            })
        },
        ...shepherdOptions,
        buttons: [
            {
                text: opts.firstStep ? doneLabel : backLabel,
                action: opts.firstStep ? tour.cancel : tour.back
            },
            {
                text: opts.lastStep ? doneLabel : nextLabel,
                action: opts.lastStep ? tour.complete : tour.next,
            }
        ],
    })
}
