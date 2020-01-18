import 'bootstrap/js/dist/modal';
import hljs from 'highlight.js/lib/highlight';
import $ from 'jquery';

// Configure highlight.js
hljs.registerLanguage('php', require('highlight.js/lib/languages/php'));
$(document).ready(function () {
    // Row toggle
    $('body').on('click', '.route-browser-row', function (event) {
        if ($(event.target).closest('.route-browser-link').length === 0) {
            const $row = $(this);
            const $detailsRow = $row.next('.route-browser-details-row');
            const $details = $detailsRow.find('.route-browser-details');

            if ($row.hasClass('table-primary')) {
                $row.removeClass('table-primary');
                $details.slideUp(130, function () {
                    $detailsRow.hide();
                });
            } else {
                $row.addClass('table-primary');
                $detailsRow.show();
                $details.slideDown(130);
            }
        }
    });

    // Source code modal
    $('#source-code').on('show.bs.modal', function (event) {
        const $link = $(event.relatedTarget);
        const code = $link.data('code');
        let $code = $('#source-code-code');
        $code.text(code).addClass('language-php');
        hljs.highlightBlock($code[0]);
    });
})
