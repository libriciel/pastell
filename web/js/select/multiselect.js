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
        })
        .on('select2:select select2:unselect', function () {
            const search = $(this).data('select2').$container.find('.select2-search__field')[0];
            if (search) {
                setTimeout(() => search.focus());
            }
        });

    $(document).on('focusin', '.select2-multiselect-selection .select2-search__field', function () {
        this.readOnly = true;
    });

    document.addEventListener('keydown', function (e) {
        const field = e.target;
        if (!(field instanceof Element) || !field.classList.contains('select2-search__field')) {
            return;
        }
        const selection = field.closest('.select2-multiselect-selection');
        if (!selection || e.key !== 'Backspace' || field.value !== '') {
            return;
        }
        if (selection.querySelectorAll('.select2-selection__choice').length === 0) {
            return;
        }
        const container = field.closest('.select2-container');
        const select = container && container.previousElementSibling;
        if (!select || select.tagName !== 'SELECT') {
            return;
        }
        const $select = $(select);
        const values = $select.val() || [];
        if (values.length === 0) {
            return;
        }
        values.pop();
        $select.val(values).trigger('change');
        e.preventDefault();
        e.stopImmediatePropagation();
    }, true);
});
