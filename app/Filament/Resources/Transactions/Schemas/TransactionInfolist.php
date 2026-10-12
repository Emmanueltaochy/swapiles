<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Transaction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Tableau de bord d'une vente : synthèse et alertes en haut, puis l'article,
 * la livraison et le parcours à gauche ; les personnes et l'argent à droite.
 * Les chiffres viennent de App\Support\FicheTransaction (testé).
 */
class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $vue = fn (string $nom, string $vue, array $donnees = []) => ViewEntry::make($nom)->hiddenLabel()->view($vue)->viewData($donnees);

        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make([$vue('synthese', 'filament.transactions.synthese')])
                            ->columnSpanFull(),

                        Group::make([
                            Section::make("L'article")
                                ->icon('heroicon-o-shopping-bag')
                                ->schema([$vue('article', 'filament.transactions.article')]),

                            Section::make('Livraison')
                                ->icon('heroicon-o-truck')
                                ->schema([$vue('livraison', 'filament.transactions.livraison')]),

                            Section::make('Parcours de la vente')
                                ->description('Achat → paiement → expédition / remise → réception → versement')
                                ->icon('heroicon-o-map')
                                ->schema([$vue('cheminement', 'filament.infolists.transaction-timeline')]),

                            Section::make('Qui était en contact avec le vendeur pour cette annonce ?')
                                ->description("L'acheteur n'est pas connu : voici les membres qui ont écrit au vendeur ou mis l'annonce en favori. Si personne, c'est probablement une annonce retirée ou vendue ailleurs.")
                                ->icon('heroicon-o-user-group')
                                ->visible(fn (Transaction $record) => $record->estDeclaree() && ! $record->buyer_id && $record->listing)
                                ->schema([$vue('contacts', 'filament.infolists.transaction-contacts')]),

                            Section::make('Échanges entre l’acheteur et le vendeur')
                                ->description('Messages à propos de cette annonce. À consulter en cas de litige.')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->collapsed()
                                ->visible(fn (Transaction $record) => $record->buyer_id !== null)
                                ->schema([$vue('conversation', 'filament.transactions.conversation')]),
                        ])->columnSpan(['lg' => 2]),

                        Group::make([
                            Section::make('Acheteur')
                                ->icon('heroicon-o-user')
                                ->schema([$vue('acheteur', 'filament.transactions.personne', ['role' => 'acheteur'])]),

                            Section::make('Vendeur')
                                ->icon('heroicon-o-building-storefront')
                                ->schema([$vue('vendeur', 'filament.transactions.personne', ['role' => 'vendeur'])]),

                            Section::make('Argent')
                                ->icon('heroicon-o-banknotes')
                                ->schema([$vue('argent', 'filament.transactions.argent')]),

                            Section::make('Avis')
                                ->icon('heroicon-o-star')
                                ->schema([$vue('avis', 'filament.transactions.avis')]),
                        ])->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }
}
