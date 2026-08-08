<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lead->property?->full_address ?: 'Property photos' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fb;
            color: #14233b
        }

        .wrap {
            max-width: 1100px;
            margin: auto;
            padding: 28px 16px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px
        }

        .photo {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 5px #0002
        }

        .photo img {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block
        }

        .caption {
            padding: 10px;
            color: #52606d;
            font-size: 14px
        }
    </style>
</head>

<body>
    <main class="wrap">
        <h1>Property Photos</h1>
        <p>{{ $lead->property?->full_address }}</p>
        <div class="grid">
            @forelse($lead->photos as $photo)
                <article class="photo"><a href="{{ $photo->url }}" target="_blank" rel="noopener"><img
                            src="{{ $photo->url }}" alt="Property photo"></a>
                    @if ($photo->caption)
                        <div class="caption">{{ $photo->caption }}</div>
                    @endif
                </article>
            @empty<p>No photos are currently available.</p>
            @endforelse
        </div>
    </main>
</body>

</html>
