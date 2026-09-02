$(document).ready(function () {
    $('.select2-multiselect').select2({
        width: '100%',
        closeOnSelect: false,
        selectionCssClass: 'select2-multiselect-selection',
        dropdownCssClass: 'select2-multiselect-dropdown'
    });

    $('.select2-multiselect')
        .on('select2:unselecting', function () {
            const $select = $(this);
            $select.data('unselecting', true);
            setTimeout(() => $select.removeData('unselecting'));
        })
        .on('select2:opening', function (e) {
            if ($(this).data('unselecting')) {
                $(this).removeData('unselecting');
                e.preventDefault();
            }
        });
});
