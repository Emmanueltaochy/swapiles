<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111827;">
    @foreach(preg_split("/\r?\n\s*\r?\n/", trim($texte)) as $paragraphe)
        <p style="margin:0 0 12px;">{!! nl2br(e(trim($paragraphe))) !!}</p>
    @endforeach
    @include('emails.partials.signature-emmanuel')
</body>
</html>
