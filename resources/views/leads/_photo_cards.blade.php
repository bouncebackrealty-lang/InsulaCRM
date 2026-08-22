@foreach($photos as $photo)
<div class="col-6 col-sm-4 col-md-3" data-photo-id="{{ $photo->id }}">
    <div class="position-relative" style="border-radius:6px;overflow:hidden;">
        <a href="{{ $photo->url }}" target="_blank" class="d-block photo-thumb" data-caption="{{ $photo->caption }}" data-original="{{ $photo->original_name }}">
            <img src="{{ $photo->url }}" alt="{{ $photo->caption ?? $photo->original_name }}"
                 class="w-100" style="height:140px;object-fit:cover;cursor:pointer;border-radius:6px;">
        </a>
        <form action="{{ route('leads.photos.delete', [$lead, $photo]) }}" method="POST"
              class="position-absolute" style="top:4px;right:4px;"
              onsubmit="return confirm('{{ __('Delete this photo?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-icon" style="background:rgba(0,0,0,0.5);border:none;padding:2px 5px;" title="{{ __('Delete') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="#fff" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </form>
        @if($photo->caption)
        <div class="position-absolute w-100 px-2 py-1" style="bottom:0;left:0;background:rgba(0,0,0,0.55);color:#fff;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ $photo->caption }}
        </div>
        @endif
    </div>
    <div class="mt-1">
        <small class="text-secondary" title="{{ $photo->original_name }}">
            {{ $photo->uploader->name ?? __('Unknown') }} &middot; {{ Fmt::date($photo->created_at) }}
        </small>
    </div>
</div>
@endforeach
