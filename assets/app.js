(function () {
    'use strict';

    var body = document.body;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var transitionDelay = reduceMotion ? 0 : 230;
    var navigating = false;

    function showPage() {
        navigating = false;
        body.classList.remove('page-leaving');

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                body.classList.add('page-ready');
            });
        });
    }

    function leavePage(callback) {
        if (navigating) {
            return;
        }

        navigating = true;
        body.classList.remove('page-ready');
        body.classList.add('page-leaving');

        window.setTimeout(callback, transitionDelay);
    }

    function isRegularInternalLink(link, event) {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            link.hasAttribute('download') ||
            link.target === '_blank'
        ) {
            return false;
        }

        var href = link.getAttribute('href');

        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }

        var destination = new URL(link.href, window.location.href);

        if (destination.origin !== window.location.origin) {
            return false;
        }

        if (
            destination.pathname === window.location.pathname &&
            destination.search === window.location.search &&
            destination.hash
        ) {
            return false;
        }

        return true;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');

        if (!link || !isRegularInternalLink(link, event)) {
            return;
        }

        event.preventDefault();

        leavePage(function () {
            window.location.href = link.href;
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || form.target === '_blank') {
            return;
        }

        event.preventDefault();

        leavePage(function () {
            HTMLFormElement.prototype.submit.call(form);
        });
    });

    window.addEventListener('pageshow', showPage);
    showPage();
}());
