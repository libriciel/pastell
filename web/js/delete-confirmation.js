$(document).ready(function () {
    var modalElement = document.getElementById('delete-confirmation-modal');
    if (modalElement === null || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = new bootstrap.Modal(modalElement);
    var $modal = $(modalElement);
    var $body = $modal.find('.modal-body');
    var $title = $modal.find('.modal-title');
    var defaultTitle = $title.text();

    function openModal(url, fetchOptions, fallback) {
        $title.text(defaultTitle);
        $body.html("<p class='text-center'>Chargement…</p>");
        modal.show();

        fetch(url, fetchOptions)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                $body.html(html);
                var modalTitle = $body.find('.delete-confirmation').data('modalTitle');
                if (modalTitle) {
                    $title.text(modalTitle);
                }
                var freshToken = $body.find('input[name="csrf_token"]').val();
                if (freshToken) {
                    $('input[name="csrf_token"]').val(freshToken);
                }
            })
            .catch(function () {
                modal.hide();
                fallback();
            });
    }

    $(document).on('click', 'a.js-delete-modal', function (event) {
        event.preventDefault();
        var url = this.href;
        openModal(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }, function () {
            window.location.href = url;
        });
    });

    $(document).on('submit', 'form.js-delete-modal', function (event) {
        event.preventDefault();
        var form = this;
        openModal(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }, function () {
            form.submit();
        });
    });

    $modal.on('click', '.js-delete-cancel', function (event) {
        event.preventDefault();
        modal.hide();
    });
});
