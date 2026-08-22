<div class="card mb-3" id="photos-section">
    <div class="card-header">
        <h3 class="card-title">{{ __('Photos') }}</h3>
        <div class="card-actions">
            <span class="text-secondary"><span id="photo-total-count">{{ $lead->photos->count() }}</span> {{ __('photo(s)') }}</span>
            <a class="btn btn-ghost-secondary btn-sm" data-bs-toggle="collapse" href="#section-photos" aria-expanded="true" aria-label="{{ __('Toggle section') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="6 9 12 15 18 9"/></svg>
            </a>
        </div>
    </div>
    <div class="card-body collapse show" id="section-photos">
        <div class="row g-2 mb-3" id="photo-gallery" @if(!$lead->photos->count()) style="display:none;" @endif>
            @include('leads._photo_cards', ['photos' => $lead->photos])
        </div>

        <!-- Upload Form -->
        <form action="{{ route('leads.photos.upload', $lead) }}" method="POST" enctype="multipart/form-data" id="photo-upload-form">
            @csrf
            <div class="mb-2">
                <div id="photo-drop-zone" class="border border-2 border-dashed rounded text-center py-3 px-2" style="cursor:pointer;border-color:#c5d2de !important;transition:background 0.15s;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler mb-1 text-secondary" width="24" height="24" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/><path d="M6 18l3.5 -4a.9 .9 0 0 1 1.1 0l2.4 2l3.5 -4.5a.9 .9 0 0 1 1.1 0l2.4 3"/></svg>
                    <div class="text-secondary" style="font-size:13px;">
                        {{ __('Drop photos here or') }} <strong class="text-primary">{{ __('click to browse') }}</strong>
                    </div>
                    <small class="text-secondary">{{ __('JPG, PNG, GIF, WebP. Max 10MB each.') }}</small>
                    <input type="file" name="photos[]" id="photo-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                </div>
            </div>
            <div id="photo-preview-area" class="row g-2 mb-2" style="display:none;"></div>
            <div id="photo-upload-actions" style="display:none;" class="d-flex justify-content-between align-items-center">
                <span class="text-secondary" id="photo-count-label">{{ __('0 selected') }}</span>
                <div>
                    <button type="button" class="btn btn-ghost-secondary btn-sm" id="photo-clear-btn">{{ __('Clear') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="photo-upload-submit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                        {{ __('Upload Photos') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Photo Lightbox Modal -->
<div class="modal modal-blur fade" id="photo-lightbox" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:transparent;border:none;box-shadow:none;">
            <div class="text-end mb-1">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <img src="" id="lightbox-img" class="w-100 rounded" style="max-height:80vh;object-fit:contain;">
            <div class="text-center text-white mt-2" id="lightbox-caption"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropZone = document.getElementById('photo-drop-zone');
    var fileInput = document.getElementById('photo-file-input');
    var previewArea = document.getElementById('photo-preview-area');
    var actions = document.getElementById('photo-upload-actions');
    var countLabel = document.getElementById('photo-count-label');
    var clearBtn = document.getElementById('photo-clear-btn');
    var form = document.getElementById('photo-upload-form');
    var submitBtn = document.getElementById('photo-upload-submit');
    var gallery = document.getElementById('photo-gallery');
    var totalCount = document.getElementById('photo-total-count');
    var selectedFiles = [];

    // Click to browse
    dropZone.addEventListener('click', function() { fileInput.click(); });

    // Drag & drop
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.style.background = '#e8f0fe';
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.style.background = '';
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.style.background = '';
        selectedFiles = Array.prototype.slice.call(e.dataTransfer.files || []);
        fileInput.value = '';
        showPreviews();
    });

    fileInput.addEventListener('change', function() {
        selectedFiles = Array.prototype.slice.call(fileInput.files || []);
        showPreviews();
    });

    function showPreviews() {
        previewArea.innerHTML = '';
        var files = selectedFiles;
        if (!files.length) {
            previewArea.style.display = 'none';
            actions.style.display = 'none';
            return;
        }

        previewArea.style.display = 'flex';
        actions.style.display = 'flex';
        countLabel.textContent = files.length + ' {{ __('selected') }}';

        for (var i = 0; i < files.length; i++) {
            (function(index) {
                var file = files[index];
                var col = document.createElement('div');
                col.className = 'col-6 col-sm-4 col-md-3';

                var reader = new FileReader();
                reader.onload = function(e) {
                    col.innerHTML = '<img src="' + e.target.result + '" class="w-100 rounded" style="height:100px;object-fit:cover;">' +
                        '<input type="text" name="captions[' + index + ']" class="form-control form-control-sm mt-1" placeholder="{{ __('Caption (optional)') }}" style="font-size:11px;">';
                    previewArea.appendChild(col);
                };
                reader.readAsDataURL(file);
            })(i);
        }
    }

    clearBtn.addEventListener('click', function() {
        fileInput.value = '';
        selectedFiles = [];
        previewArea.innerHTML = '';
        previewArea.style.display = 'none';
        actions.style.display = 'none';
    });

    form.addEventListener('submit', function(e) {
        if (!selectedFiles.length || !window.fetch) {
            return;
        }

        e.preventDefault();
        uploadSequentially(selectedFiles);
    });

    function captionFor(index) {
        var input = previewArea.querySelector('input[name="captions[' + index + ']"]');
        return input ? input.value : '';
    }

    function setUploadingState(isUploading, label) {
        if (submitBtn) {
            submitBtn.disabled = isUploading;
        }
        if (clearBtn) {
            clearBtn.disabled = isUploading;
        }
        countLabel.textContent = label;
    }

    function appendUploadedPhotos(data) {
        if (gallery && data.photos_html) {
            gallery.insertAdjacentHTML('beforeend', data.photos_html);
            gallery.style.display = 'flex';
        }
        if (totalCount && typeof data.photo_count !== 'undefined') {
            totalCount.textContent = data.photo_count;
        }
    }

    function uploadSequentially(files) {
        var token = form.querySelector('input[name="_token"]').value;
        var total = files.length;
        var uploaded = 0;

        function sendNext(index) {
            var payload = new FormData();
            payload.append('_token', token);
            payload.append('photos[]', files[index]);
            payload.append('captions[]', captionFor(index));

            return fetch(form.action, {
                method: 'POST',
                body: payload,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('Upload failed');
                }

                return response.json();
            }).then(function(data) {
                appendUploadedPhotos(data);

                uploaded++;
                setUploadingState(true, uploaded + ' / ' + total + ' {{ __('uploaded') }}');

                if (uploaded < total) {
                    return sendNext(index + 1);
                }

                fileInput.value = '';
                selectedFiles = [];
                previewArea.innerHTML = '';
                previewArea.style.display = 'none';
                actions.style.display = 'none';
                setUploadingState(false, uploaded + ' {{ __('photo(s) uploaded') }}');
            });
        }

        setUploadingState(true, '{{ __('Uploading photos...') }}');
        sendNext(0).catch(function(error) {
            setUploadingState(false, error.message || '{{ __('Upload failed. Please try again.') }}');
        });
    }

    // Lightbox
    var lightboxModal = document.getElementById('photo-lightbox');
    if (lightboxModal) {
        gallery?.addEventListener('click', function(e) {
            var link = e.target.closest('.photo-thumb');
            if (link) {
                e.preventDefault();
                document.getElementById('lightbox-img').src = link.href;
                var caption = link.dataset.caption || link.dataset.original || '';
                document.getElementById('lightbox-caption').textContent = caption;
                new bootstrap.Modal(lightboxModal).show();
            }
        });
    }
});
</script>
@endpush
