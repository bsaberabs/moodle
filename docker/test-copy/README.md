# Copie de test d'Oriskill

Une copie de la production sur le même serveur, à l'adresse https://test.oriskill.com, pour tester une nouvelle
version (branche `dev`) sur les vraies données avant de la mettre en ligne.

- Dossier `/opt/moodle-test`, projet Docker `moodle-test` : base, fichiers et conteneurs séparés de la production
  (port local 8081). La production n'est que lue, au moment d'une recopie.
- Protégée par un mot de passe (Caddy). Seules les notifications de paiement passent sans lui.
- Rien de réel n'en sort (`MOODLE_STAGING=true`) :
  - tous les e-mails restent dans la boîte de test : https://test.oriskill.com/mailpit/ ;
  - SecureAndPay est toujours en mode test, et les comptes Stripe / PayPal réels sont retirés de la copie ;
  - les webhooks et ActiveCampaign ne sont pas appelés.
- Un bandeau rouge « COPIE DE TEST » est affiché en haut de chaque page.

## Installation (une fois)

1. Chez le registraire du domaine, créer un enregistrement A `test.oriskill.com` vers `91.214.190.221`.
2. Depuis le PC :

   ```
   scp -i ~/.ssh/deploy_moodle -r docker/test-copy ubuntu@91.214.190.221:/tmp/
   ssh -i ~/.ssh/deploy_moodle ubuntu@91.214.190.221 "sudo bash /tmp/test-copy/install.sh"
   ```

   Le script libère de la place sur le disque système, crée `/opt/moodle-test`, ajoute le site à Caddy, recopie la
   production et affiche l'identifiant et le mot de passe (gardés dans `/opt/moodle-test/acces-copie-test.txt`).

## Ensuite

- Chaque envoi sur la branche `dev` met à jour le code de la copie (CI, tâche « Mettre à jour la copie de test »).
- Pour repartir des données actuelles de la production : `oriskill-test-refresh` sur le serveur. Tout ce qui a été
  fait sur la copie est alors effacé.
