$(document).ready(function () {
    $(".noautocomplete").attr('autocomplete', 'off');

    $(".send_button").click(function () {
        $(this.form).append("<input type='hidden' name='" + this.name + "' value='true' />");
        $(this.form).append("<p>Sauvegarde en cours ...</p>");
        $(".send_button").attr('disabled', true);
        $(this.form).submit();
    });

    $('#select-all').click(function (event) {
        var result = this.checked;
        $(':checkbox').each(function () {
            this.checked = result;
        });
    });

    $.fn.select2.defaults.set("language", "fr");

    $(".select2_entite").select2({
        placeholder: 'Sélectionner une entité',
        width: "25%"
    });

    $(".select2_role").select2({
        placeholder: 'Sélectionner un rôle',
        width: "25%"
    });

    $(".select2_document").select2({
        placeholder: 'Sélectionner un type de dossier'
    });

    $('.select2_breadcrumb').select2({
        placeholder: 'Sélectionner une entité fille',
        allowClear: true
    });

    $('.select2_etat').select2({
        placeholder: 'Sélectionner un état'
    });

    $('[data-toggle="tooltip"]').tooltip()


    $('.collapse-link').click(function () {
        $(this).find("i").toggleClass('fa-plus-square');
        $(this).find("i").toggleClass('fa-minus-square');
    });


    $(".fa-calendar").click(function () {
        var input = $(this).parents(".input-group").find("input");
        if (input.datepicker("widget").is(":visible")) {
            input.datepicker('hide');
        } else {
            input.datepicker('show');
        }
    });
});

function split(val) {
    return val.split(/,\s*/);
}

function extractLast(term) {
    return split(term).pop();
}

(function ($) {

    $.fn.pastellAutocomplete = function (autocomplete_url, id_e, mail_only) {
        this.autocomplete({
            source: function (request, response) {
                $.getJSON(autocomplete_url, {
                    term: extractLast(request.term), "id_e": id_e, "mail-only": mail_only
                }, response);
            },
            select: function (event, ui) {
                var terms = split(this.value);
                terms.pop();
                terms.push(ui.item.value);
                terms.push("");
                this.value = terms.join(", ");
                return false;
            },
        });
        return this;
    };

}(jQuery));
