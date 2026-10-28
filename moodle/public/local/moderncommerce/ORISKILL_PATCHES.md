# Modifications Oriskill de Modern Commerce

Ces changements ne viennent pas du dépôt d'origine. À reporter après chaque mise à jour du plugin.

1. **Traduction française** : `lang/fr/local_moderncommerce.php` (faite pour Oriskill).
2. **Hook `filter_payment_methods`** : permet à un autre plugin de retirer des moyens de paiement pour une
   commande ou un panier. Oriskill l'utilise pour n'offrir que SecureAndPay sur les formations d'un formateur
   (seul moyen qui encaisse sur le compte du formateur).
   - `classes/hook/filter_payment_methods.php` (nouveau)
   - `classes/payment/gateway_manager.php` : `get_payment_methods(?object $order)` déclenche le hook,
     `is_payment_method_available(..., ?object $order)`, et `initialize_payment()` refuse un moyen retiré.
   - `classes/external/checkout/place_order.php` : vérifie le moyen choisi sur la commande reprise.
   - `classes/external/checkout/checkout_helper.php` : la page de paiement (application) reçoit les moyens
     filtrés pour la commande reprise ou le panier.
   - `classes/output/checkout_page.php` : passe la commande reprise à `get_payment_methods()`.
