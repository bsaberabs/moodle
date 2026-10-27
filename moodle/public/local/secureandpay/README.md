# SecureAndPay pour Modern Commerce (`local_secureandpay`)

Passerelle de paiement SecureAndPay pour la boutique Modern Commerce : paiements en une fois et
abonnements (ClicktoPay, prélevés par SecureAndPay).

## Configuration

1. Demander au support SecureAndPay (webmaster@secureandpay.com) :
   - l'identifiant du site (`IdSite`) et la clé de signature ;
   - la déclaration du domaine du site Moodle (sinon erreur 021) ;
   - l'URL de notification : `https://<site>/local/secureandpay/notify.php`.
2. Dans Moodle : *Modern Commerce → Passerelles → Carte bancaire (SecureAndPay)* :
   - **ID client marchand** : l'`IdSite` ;
   - **Clé secrète** : la clé de signature ;
   - **Mode test** : coché pour `https://www.secureandpay.com:4443`, décoché pour la production ;
   - **Activé** : coché.

## Fonctionnement

| Étape | Détail |
|---|---|
| Paiement | `pay.php` poste le formulaire signé (`data` base64 + `signature` HMAC-SHA256) à `demandePaiement.php` |
| Retour client | `urlOk` → page de confirmation de commande Modern Commerce |
| Notification | `notify.php` vérifie la signature et applique le `TransStatus` |
| Abonnement | `Clicktopay=1`, prix de la formule tous les 30 j (mensuel) ou 365 j (annuel), sans fin |

`TransStatus` : `00`/`02` payé (ou échéance suivante d'un abonnement : prolongation d'un cycle),
`01` en attente, `03` remboursé, `04` impayé (chargeback), `05` refusé, `06` abonnement arrêté
(l'accès reste ouvert jusqu'à la fin de la période payée).

## Limites

- SecureAndPay n'a pas d'API pour arrêter un abonnement : une résiliation faite dans Moodle envoie
  un e-mail aux administrateurs, qui doivent l'arrêter dans le back-office SecureAndPay.
- Les périodes d'essai gratuites des formules ne sont pas gérées (montant nul refusé).
- Devises : EUR, USD, GBP.
