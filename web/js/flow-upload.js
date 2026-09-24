(function ($) {
    'use strict';

    const CONTROLS_TEMPLATE = `
        <div class="flow-error">
            <input type="file" class="btn btn-outline-primary"/>
        </div>
        <div>
            <a class="flow-browse btn btn-outline-primary"><i class="fa fa-plus-circle"></i>&nbsp;</a>
            <a href="#" class="progress-resume-link btn">Reprendre</a>
            <a href="#" class="progress-pause-link btn">Pause</a>
            <a href="#" class="progress-cancel-link btn">Abandon</a>
        </div>
        <div class="flow-progress">
            <table>
                <tr>
                    <td><div class="progress-container"><div class="progress-bar"></div></div></td>
                </tr>
                <tr>
                    <td><ul class="flow-list unstyled"></ul></td>
                </tr>
            </table>
        </div>`;

    const FILE_ROW_TEMPLATE = '<li class="flow-file"><span class="flow-file-size"></span> <span class="flow-file-progress"></span> </li>';

    const DURATION_UNITS = [
        [31536000, 'an'],
        [86400, 'jour'],
        [3600, 'heure'],
        [60, 'minute'],
        [1, 'seconde'],
    ];

    const SIZE_UNITS = ['octets', 'ko', 'Mo', 'Go', 'To', 'Po'];

    /**
     * The server rotates the CSRF token on each chunk request and sends the new one back:
     * it must be stored before the next chunk is sent.
     */
    function updateCsrfToken(responseText) {
        if (!responseText) {
            return;
        }
        let token;
        try {
            token = JSON.parse(responseText).csrf_token;
        } catch (err) {
            console.error('Non-JSON response received from chunk upload', err);
            return;
        }
        if (token) {
            $('input[name="csrf_token"]').val(token);
        }
    }

    function formatBytes(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 octet';
        }
        const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), SIZE_UNITS.length - 1);
        return `${(bytes / 1024 ** exponent).toFixed(2)} ${SIZE_UNITS[exponent]}`;
    }

    function formatDuration(seconds) {
        if (!Number.isFinite(seconds)) {
            return '';
        }
        for (const [unitSize, label] of DURATION_UNITS) {
            const value = Math.floor(seconds / unitSize);
            if (value >= 1) {
                return `${value} ${label}${value > 1 ? 's' : ''}`;
            }
        }
        return '0 seconde';
    }

    function renderControls($container, queryParam) {
        $container.html(CONTROLS_TEMPLATE);
        $container.find('.flow-error input').attr({
            id: queryParam.field,
            name: queryParam.field,
            accept: queryParam.accept,
        });
        const label = queryParam.single_file ? 'Ajouter un fichier' : 'Ajouter un (ou des) fichier(s)';
        $container.find('.flow-browse').append(document.createTextNode(label));
    }

    function submitForm($container, field) {
        $container.closest('form')
            .append($('<input>', {type: 'hidden', name: 'fieldSubmittedId', value: field}))
            .append($('<input>', {type: 'hidden', name: 'ajouter', value: 'ajouter'}))
            .trigger('submit');
    }

    window.addFlowControl = function (queryParam, container) {
        const $container = $(container);
        renderControls($container, queryParam);

        const $resumeLink = $container.find('.progress-resume-link');
        const $pauseLink = $container.find('.progress-pause-link');
        const $cancelLink = $container.find('.progress-cancel-link');
        const $progress = $container.find('.flow-progress');
        const $progressBar = $container.find('.progress-bar');
        const $fileList = $container.find('.flow-list');
        const fileProgressCells = new Map();

        let pauseRequested = false;
        let cancelRequested = false;
        let submitted = false;

        const flow = new Flow({
            target: queryParam.target,
            query: (file) => ({
                id_e: queryParam.id_e,
                id_d: queryParam.id_d,
                id_ce: queryParam.id_ce,
                field: queryParam.field,
                key: queryParam.key,
                page: queryParam.page,
                filename: file.name,
                num: flow.files.indexOf(file),
                csrf_token: $('input[name="csrf_token"]').val(),
            }),
            singleFile: queryParam.single_file,
            chunkSize: 1024 * 1024,
            testChunks: true,
            simultaneousUploads: 1,
        });

        flow.assignBrowse($container.find('.flow-browse')[0], false, queryParam.single_file, {accept: queryParam.accept});

        function cancelUpload() {
            flow.cancel();
            pauseRequested = false;
            cancelRequested = false;
            $pauseLink.text('Pause');
            $cancelLink.text('Abandon');
            $resumeLink.add($pauseLink).add($cancelLink).add($progress).hide();
            $fileList.empty();
            fileProgressCells.clear();
            $container.removeClass('flow-upload-failed');
        }

        // Pausing or cancelling immediately would abort the running request and lose the rotated CSRF token:
        // both are applied once the current chunk has been acknowledged.
        $pauseLink.on('click', function (event) {
            event.preventDefault();
            pauseRequested = true;
            $pauseLink.text('Arrêt en cours...');
        });

        $resumeLink.on('click', function (event) {
            event.preventDefault();
            flow.resume();
            $resumeLink.hide();
            $pauseLink.show();
        });

        $cancelLink.on('click', function (event) {
            event.preventDefault();
            if (!flow.isUploading()) {
                cancelUpload();
                return;
            }
            cancelRequested = true;
            $cancelLink.text('Abandon en cours...');
            $pauseLink.hide();
        });

        flow.on('fileAdded', function (file) {
            $progress.show();
            $fileList.show();
            const $row = $(FILE_ROW_TEMPLATE).prepend(document.createTextNode(file.name));
            fileProgressCells.set(file, $row.find('.flow-file-progress'));
            $fileList.append($row);
        });

        flow.on('filesSubmitted', function () {
            // Failed files are expected to be added again: they must not prevent the form from being submitted
            flow.files.filter((file) => file.error).forEach((file) => flow.removeFile(file));
            $container.removeClass('flow-upload-failed');

            // The resume link is hidden as soon as the new files start: paused files would never be resumed
            if (flow.files.some((file) => file.paused)) {
                pauseRequested = false;
                $pauseLink.text('Pause');
                flow.resume();
                return;
            }
            flow.upload();
        });

        flow.on('uploadStart', function () {
            $resumeLink.hide();
            $pauseLink.show();
            $cancelLink.show();
        });

        flow.on('fileProgress', function (file, chunk) {
            if (chunk && chunk.status() === 'success') {
                updateCsrfToken(chunk.xhr.responseText);
                if (cancelRequested) {
                    cancelUpload();
                    return;
                }
                if (pauseRequested) {
                    pauseRequested = false;
                    flow.pause();
                    $pauseLink.text('Pause').hide();
                    $resumeLink.show();
                }
            }

            const remaining = formatDuration(file.timeRemaining());
            let progressText = `${Math.floor(file.progress() * 100)}% ${formatBytes(file.averageSpeed)}/s`;
            if (remaining) {
                progressText += ` ${remaining} restante(s)`;
            }
            fileProgressCells.get(file)?.text(progressText);
            $progressBar.css({width: `${Math.floor(flow.progress() * 100)}%`});
        });

        flow.on('fileSuccess', function (file) {
            fileProgressCells.get(file)?.text('(terminé)');
        });

        flow.on('fileError', function (file, message) {
            if (cancelRequested) {
                cancelUpload();
                return;
            }
            fileProgressCells.get(file)?.text(`(le fichier n'a pas pu être chargé : ${message})`);
        });

        flow.on('complete', function () {
            // After a cancellation, Flow still fires "complete" on an empty file list
            if (submitted || flow.files.length === 0 || flow.files.some((file) => !file.isComplete())) {
                return;
            }
            $resumeLink.add($pauseLink).add($cancelLink).hide();

            // Submitting reloads the page and would hide which files failed: they have to be added again
            if (flow.files.some((file) => file.error)) {
                $container.addClass('flow-upload-failed');
                return;
            }

            // Wait for the uploads of the other fields of the form, and keep their failures visible, before submitting it
            if ($('.progress-cancel-link:visible').length === 0 && $('.flow-upload-failed').length === 0) {
                submitted = true;
                submitForm($container, queryParam.field);
            }
        });
    };
}(jQuery));
