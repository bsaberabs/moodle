# SecureAndPay pour Modern Commerce (`local_secureandpay`)

Passerelle de paiement SecureAndPay pour la boutique Modern Commerce : paiements en une fois,
abonnements (ClicktoPay, prélevés par SecureAndPay), essais gratuits et paiement en un clic (upsell).

## Configuration

1. Demander au support SecureAndPay (webmaster@secureandpay.com) :
   - l'identifiant du site (`IdSite`) et la clé de signature ;
   - la déclaration du domaine du site Moodle (sinon erreur 021) ;
   - l'URL de notification : `https://<site>/local/secureandpay/notify.php`.
2. Dans Moodle : *Modern Commerce → Passerelles → Carte bancaire (SecureAndPay)* :
   - **ID client marchand** : l'`IdSite` (sinon, la **Clé publique** est utilisée comme `IdSite`) ;
   - **Clé publique** : obligatoire dans le formulaire, non utilisée si l'ID client marchand est rempli ;
   - **Clé secrète** : la clé de signature ;
   - **Mode test** : coché pour `https://www.secureandpay.com:4443`, décoché pour la production ;
   - **Activé** : coché.

   Le *Secret webhook* reste vide : les notifications sont signées avec la clé secrète.
3. Dans *Administration du site → Plugins → Plugins locaux → SecureAndPay* :
   - **Essais gratuits avec carte enregistrée** (activé par défaut) ;
   - **Paiement en un clic (upsell)** (désactivé par défaut).

## Fonctionnement

| Cas | Envoyé à SecureAndPay |
|---|---|
| Achat simple | `Clicktopay=0`, montant de la commande |
| Abonnement | `Clicktopay=1`, prix de la formule tous les 30 j (mensuel) ou 365 j (annuel), `ReccurentNumber=X` |
| Essai gratuit | idem abonnement avec `Amount=0` et `ReccurentTestPeriod` = jours d'essai |
| Achat, un clic activé, sans carte | `Clicktopay=1`, `ReccurentNumber=1` : carte enregistrée, débitée une seule fois |
| Achat ou abonnement, carte enregistrée | `Clicktopay=2` + `token` : le client confirme sans ressaisir sa carte |

- **Essai gratuit** : le bouton « Commencer l'essai gratuit » de Modern Commerce est redirigé vers
  `trial.php`, qui crée une commande à 0 €. À la notification, l'abonnement passe en essai ; il
  devient actif au premier prélèvement de SecureAndPay. L'essai Moodle dure un jour de plus que
  celui de SecureAndPay, pour ne pas expirer avant que ce prélèvement soit notifié.
- **Notification** (`notify.php`), `TransStatus` : `00`/`02` payé (ou échéance suivante d'un
  abonnement : prolongation d'un cycle, ou réactivation si Moodle l'avait expiré), `01` en
  attente, `03` remboursé, `04` impayé (chargeback), `05` refusé, `06` abonnement arrêté
  (l'accès reste ouvert jusqu'à la fin de la période payée).
- **Jeton** : renvoyé par SecureAndPay après un paiement ClicktoPay, conservé dans une préférence
  de l'utilisateur (avec la date d'expiration de la carte), inclus dans l'export RGPD. Aucune
  donnée de carte n'est stockée dans Moodle.

## Plusieurs comptes marchands (place de marché)

Par défaut, tout est encaissé sur le compte de la plateforme (réglages de la passerelle). Un autre
plugin peut envoyer une commande vers un autre compte SecureAndPay en écoutant le hook
`\local_secureandpay\hook\resolve_merchant` : il désigne le compte (`set_merchant()`) ou refuse le
paiement avec un message (`refuse()`). C'est ce que fait `local_oriskill` pour les formations de ses
formateurs.

- La demande de paiement est signée avec la clé du compte choisi, et l'IdSite de ce compte est envoyé.
- Une notification est vérifiée avec la clé du compte qui a encaissé la commande : la commande est
  retrouvée d'abord (lecture seule), rien n'est modifié avant la vérification de la signature.
- Les jetons de carte (paiement en un clic) sont rangés par compte : un jeton enregistré chez un
  vendeur ne sert jamais chez un autre.

## Limites

- SecureAndPay n'a pas d'API pour arrêter un abonnement : une résiliation faite dans Moodle envoie
  un e-mail aux administrateurs, qui doivent l'arrêter dans le back-office SecureAndPay.
- Fréquence en jours : un abonnement « mensuel » est prélevé tous les 30 jours.
- Devises : EUR, USD, GBP.
