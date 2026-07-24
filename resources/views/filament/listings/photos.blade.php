<form method="POST" action="{{ route('admin.listing-photos.update', $listing) }}" enctype="multipart/form-data"
      style="display:flex;flex-direction:column;gap:1.1rem;">
    @csrf

    <div>
        <p style="font-weight:800;margin-bottom:.5rem;font-size:.95rem;">Photos actuelles</p>
        @if($listing->images->isEmpty())
            <p style="opacity:.6;font-size:.9rem;">Aucune photo pour cette annonce.</p>
        @else
            <p style="font-size:.8rem;opacity:.6;margin-bottom:.6rem;">Cochez la case 🗑️ pour supprimer une photo.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:.6rem;">
                @foreach($listing->images as $img)
                    <label style="position:relative;display:block;border:2px solid #e5e7eb;border-radius:.6rem;overflow:hidden;cursor:pointer;">
                        <img src="{{ $img->url }}" alt="" style="width:100%;height:120px;object-fit:cover;display:block;">
                        <span style="position:absolute;top:5px;left:5px;display:inline-flex;align-items:center;gap:.25rem;background:rgba(255,255,255,.92);border-radius:6px;padding:2px 6px;font-size:.75rem;font-weight:700;color:#b91c1c;">
                            <input type="checkbox" name="delete[]" value="{{ $img->id }}" style="accent-color:#dc2626;"> 🗑️
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <div>
        <p style="font-weight:800;margin-bottom:.5rem;font-size:.95rem;">Ajouter des photos</p>
        <input type="file" name="images[]" accept="image/*" multiple
               style="font-size:.85rem;width:100%;padding:.5rem;border:1px dashed rgba(148,163,184,.5);border-radius:.6rem;">
        <p style="font-size:.72rem;opacity:.55;margin-top:.3rem;">JPG/PNG, 5 Mo max par photo.</p>
    </div>

    <button type="submit"
            style="background:#0d9488;color:#fff;font-weight:800;padding:.75rem 1.2rem;border-radius:.7rem;border:none;cursor:pointer;">
        💾 Enregistrer les photos
    </button>
</form>
