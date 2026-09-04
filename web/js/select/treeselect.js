document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('script.treeselect-config').forEach(function (configElement) {
        try {
            const config = JSON.parse(configElement.textContent);

            const container = document.querySelector('.' + config.containerClass);
            const input = document.getElementById(config.inputId);
            if (!container || !input) {
                return;
            }

            const treeselect = new Treeselect({
                placeholder: config.placeholder,
                parentHtmlContainer: container,
                options: config.options,
                value: input.value !== '' ? [input.value] : [],
                isSingleSelect: true,
                showTags: false,
                openLevel: config.openLevel,
            });

            treeselect.srcElement.addEventListener('input', function (e) {
                input.value = e.detail;
            });
        } catch (e) {
            console.error('Treeselect init failed', e);
        }
    });
});
