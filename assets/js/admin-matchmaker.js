/**
 * Matchmaker Admin Portal — admin-matchmaker.js
 * Loaded only on the matchmaking-pool admin page.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /* Confirm dialog before reject actions */
        var rejectLinks = document.querySelectorAll('.mm-reject-link');
        rejectLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (!window.confirm('Are you sure you want to reject this match? This cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    });
}());
