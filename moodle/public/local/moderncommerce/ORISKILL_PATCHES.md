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
3. **Lien « Learner dashboard » du pied de page** : `classes/storefront/seed.php` pointait vers
   `learner/dashboard.php`, qui n'existe pas ; corrigé en `learner/index.php`.
4. **Hook `resolve_gateway_config`** : permet d'encaisser un achat sur le compte Stripe ou PayPal d'un vendeur
   (formateur Oriskill) au lieu de celui de la boutique.
   - `classes/hook/resolve_gateway_config.php` (nouveau)
   - `classes/payment/gateway_manager.php` : contexte de paiement (`set_payment_context()`), posé pour la liste
     des moyens de paiement, le démarrage du paiement, le retour du navigateur et les webhooks ;
     `get_gateway_config()` déclenche le hook dans ce contexte ; un moyen désactivé pour la boutique est proposé
     si le compte du vendeur l'accepte ; `record_payment_result()` refuse un paiement reçu sur un compte pour
     une commande d'un autre compte ; `webhook_url($gateway, $account)`.
   - `payment/webhook.php` : transmet le paramètre `account` de l'adresse du webhook.
5. **`global $CFG;` avant les `require_once($CFG...)` de haut de fichier** dans `classes/email_notifications.php`,
   `classes/api/bundle_enrollment_api.php`, `classes/output/mycourses_page.php`, `classes/output/redeem_page.php`,
   `classes/services/invoice_service.php`, `classes/services/program_progress_service.php`. Une fois
   `paypal_gateway.php` chargé, l'autoloader Composer du plugin (`vendor/`) inclut ces fichiers sans `$CFG` : la
   validation d'une commande payée par PayPal échouait (« Failed opening required '/local/moderncommerce/lib.php' »).
6. **Événement `subscription_cancel_scheduled`** : déclenché quand l'abonné résilie « à la fin de la période »
   (`classes/external/learner/change_subscription.php`, `set_cancel_at_period_end()`). Sans lui, une passerelle
   qui prélève elle-même (SecureAndPay ClicktoPay) n'était prévenue qu'à la fin de la période, par
   `subscription_cancelled`, et pouvait encore prélever entre les deux (fin d'un essai gratuit).
   - `classes/event/subscription_cancel_scheduled.php` (nouveau), chaîne `event:subscriptioncancelscheduled`.
