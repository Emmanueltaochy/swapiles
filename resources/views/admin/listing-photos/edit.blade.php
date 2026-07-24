<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Photos — {{ $listing->title }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #0b1220; color: #e5e7eb;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 1.5rem;
        }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 1rem; padding: 1.5rem; }
        a.back { color: #5eead4; text-decoration: none; font-weight: 700; font-size: .9rem; }
        a.back:hover { text-decoration: underline; }
        h1 { font-size: 1.25rem; margin: .75rem 0 1.25rem; }
        h2 { font-size: .95rem; font-weight: 800; margin: 0 0 .5rem; }
        .flash { background: rgba(16,185,129,.15); border: 1px solid #10b981; color: #6ee7b7;
                 padding: .75rem 1rem; border-radius: .7rem; margin-bottom: 1.25rem; font-weight: 700; }
        .hint { font-size: .8rem; opacity: .6; margin-bottom: .6rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: .6rem; }
        .thumb { position: relative; display: block; border: 2px solid #374151; border-radius: .6rem; overflow: hidden; cursor: pointer; }
        .thumb img { width: 100%; height: 130px; object-fit: cover; display: block; }
        .thumb.checked { border-color: #dc2626; }
        .tag { position: absolute; top: 5px; left: 5px; display: inline-flex; align-items: center; gap: .3rem;
               background: rgba(17,24,39,.9); border-radius: 6px; padding: 3px 7px; font-size: .78rem; font-weight: 700; color: #fca5a5; }
        .tag input { accent-color: #dc2626; width: 16px; height: 16px; }
        input[type=file] { font-size: .85rem; width: 100%; padding: .6rem; border: 1px dashed rgba(148,163,184,.5);
                           border-radius: .6rem; background: #0b1220; color: #e5e7eb; }
        .filehint { font-size: .72rem; opacity: .55; margin-top: .3rem; }
        button.save { background: #0d9488; color: #fff; font-weight: 800; padding: .85rem 1.2rem; border-radius: .7rem;
                      border: none; cursor: pointer; width: 100%; font-size: 1rem; margin-top: 1.25rem; }
        button.save:hover { background: #0f766e; }
        .block { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="{{ url('/admin/listings/' . $listing->id . '/edit') }}">← Retour à l'annonce</a>
        <h1>Gérer les photos de l'annonce<br><span style="opacity:.7;font-weight:600;font-size:1rem;">{{ $listing->title }}</span></h1>

        <div class="card">
            @if(session('status'))
                <div class="flash">✅ {{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.listing-photos.update', $listing) }}" enctype="multipart/form-data">
                @csrf

                <div class="block">
                    <h2>Photos actuelles</h2>
                    @if($listing->images->isEmpty())
                        <p class="hint">Aucune photo pour cette annonce.</p>
                    @else
                        <p class="hint">Cochez la case 🗑️ des photos à supprimer, puis enregistrez.</p>
                        <div class="grid">
                            @foreach($listing->images as $img)
                                <label class="thumb" onclick="this.classList.toggle('checked', this.querySelector('input').checked)">
                                    <img src="{{ \App\Support\ImageUrl::absolute($img->url) }}" alt="">
                                    <span class="tag" onclick="event.stopPropagation();">
                                        <input type="checkbox" name="delete[]" value="{{ $img->id }}"
                                               onchange="this.closest('.thumb').classList.toggle('checked', this.checked)"> 🗑️
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="block">
                    <h2>Ajouter des photos</h2>
                    <input type="file" name="images[]" accept="image/*" multiple>
                    <p class="filehint">JPG/PNG, 5 Mo max par photo.</p>
                </div>

                <button type="submit" class="save">💾 Enregistrer les photos</button>
            </form>
        </div>
    </div>
</body>
</html>
