<!DOCTYPE html>
<html lang="fr" class="h-full bg-stone-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Swap\'Îles') — La marketplace seconde main des îles</title>
    <meta name="description" content="@yield('description', 'Achetez, vendez, échangez et donnez en seconde main à La Réunion et dans les territoires ultramarins.')">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    

</body>
</html>

<div id="price-protection-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-[28px] max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
            <h3 class="text-xl font-extrabold text-gray-900">Détail du prix protégé</h3>
            <button type="button" onclick="closePriceProtectionModal()" class="text-3xl text-teal-700 leading-none">&times;</button>
        </div>

        <div class="p-6 space-y-5">
            <div>
                <p id="pp-title" class="font-extrabold text-gray-900 text-lg"></p>
                <p class="text-2xl font-extrabold text-gray-900"><span id="pp-price"></span> €</p>
            </div>

            <div class="flex items-start gap-4 bg-teal-50 rounded-3xl p-4">
                <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center text-xl">🛡️</div>
                <div>
                    <p class="font-extrabold text-gray-900">Protection acheteur Swap’Îles</p>
                    <p class="text-xl font-extrabold text-teal-700"><span id="pp-fee"></span> €</p>
                    <p class="text-sm text-gray-500 mt-1">
                        Cette protection permet de sécuriser l’achat, le suivi de la transaction et l’accompagnement en cas de problème.
                    </p>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                <p class="font-bold text-gray-600">Total protégé</p>
                <p class="text-2xl font-extrabold text-teal-700"><span id="pp-total"></span> €</p>
            </div>

            <p class="text-sm text-gray-500">
                Les frais de livraison Colissimo seront ajoutés séparément selon le mode choisi au paiement.
            </p>

            <button type="button" onclick="closePriceProtectionModal()" class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-5 py-4">
                OK, fermer
            </button>
        </div>
    </div>
</div>

<script>
function closePriceProtectionModal() {
    const modal = document.getElementById('price-protection-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.prix-protege');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    document.getElementById('pp-title').textContent = btn.dataset.title || 'Article';
    document.getElementById('pp-price').textContent = btn.dataset.price || '0,00';
    document.getElementById('pp-fee').textContent = btn.dataset.fee || '0,00';
    document.getElementById('pp-total').textContent = btn.dataset.total || '0,00';

    const modal = document.getElementById('price-protection-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
});
</script>

<style>
@media (max-width: 1023px) {
    
