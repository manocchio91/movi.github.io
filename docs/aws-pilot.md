# AWS pilot runbook

## Architettura minima

Una istanza EC2 `t4g.small` esegue Docker Compose con frontend statico, API PHP-FPM/Nginx, queue worker, scheduler e Redis persistente. RDS PostgreSQL `db.t4g.micro` Single-AZ resta non pubblico. Un bucket S3 privato conserva media e dump; CloudFront espone soltanto i prefissi `events/` e `organizers/`. L'instance role sostituisce chiavi AWS statiche. CloudWatch raccoglie log Docker, memoria e disco. Un Elastic IP riceve DNS Route53 o DNS esterno. Nginx host termina HTTPS con Let's Encrypt; ACM richiederebbe un ALB e non è conveniente per il pilot.

Staging e production usano gli stessi file con `DEPLOY_ENV`, parameter path, bucket, database, log group e stack Terraform distinti. Non condividere database, bucket, Redis o secret. Per contenere i costi, creare prima staging; production soltanto all'apertura del pilot.

## Stima pilot

Stima tecnica indicativa per regione Milano, 730 ore/mese e traffico basso: EC2 + EBS 20 GB circa 18–30 USD, RDS Single-AZ + 20 GB circa 20–35 USD, IPv4 pubblico circa 4 USD, S3/CloudFront/CloudWatch sotto 5–15 USD con pochi GB. Totale atteso: **47–84 USD/mese per ambiente**, esclusi dominio, IVA, traffico anomalo e SES oltre free tier. Verificare sempre AWS Pricing Calculator prima della creazione; spegnere staging quando non usato riduce EC2 ma non azzera RDS/storage/IP.

## Preparazione manuale AWS

1. Configurare budget/allarmi di fatturazione e backend remoto cifrato per lo state Terraform.
2. Copiare `terraform.tfvars.example` fuori dal repository, usare password DB casuale e applicare Terraform separatamente per `staging` e `production` solo dopo approvazione.
3. Inserire l'env completo come singolo SecureString SSM `/movi/<environment>/env`; usare endpoint RDS, output bucket e URL CloudFront. Non impostare `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` su EC2.
4. Creare record DNS A per app/API verso l'Elastic IP. Fermare temporaneamente Nginx, eseguire Certbot standalone per entrambi gli host, installare `infra/aws/nginx/movi.conf.template` sostituendo i domini, verificare rinnovo automatico.
5. Installare la configurazione CloudWatch sostituendo `ENVIRONMENT`, avviare l'agent e configurare rotazione Docker (`max-size`/`max-file`). Creare allarmi su EC2 status, CPU, memoria, disco, RDS CPU/storage/connections e health esterno.
6. Autenticare Docker a GHCR con token read-only oppure rendere i package accessibili al repository. Configurare cron root per backup e uno smoke giornaliero.

## Secret e variabili

SSM SecureString: `APP_KEY`, DB password, Redis password, URL/domìni, CORS, bucket, image tag e SMTP. GitHub Environment `staging`: `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_PRIVATE_KEY`, `STAGING_SSH_KNOWN_HOSTS`; variable `STAGING_API_URL`. Terraform secret: `db_password`, conservato fuori Git e preferibilmente passato con `TF_VAR_db_password`. SES opzionale usa endpoint SMTP regionale, porta 587/TLS, username/password SMTP e mittente verificato. Con `MAIL_MAILER=log` il fallback resta operativo.

## Sicurezza e operatività

- Security group EC2: 80/443 pubblici, SSH soltanto da un singolo CIDR amministrativo; RDS 5432 soltanto dal security group EC2. Redis e porte Docker restano su rete interna/loopback.
- `APP_DEBUG=false`, allowlist CORS esatta, cookie secure, IMDSv2, dischi/RDS/S3 cifrati, bucket senza accesso pubblico, IAM least privilege.
- Non loggare token, password, dati sanitari o contenuti prenotazione. Retention CloudWatch 14 giorni.
- Health: `/api/v1/health` verifica DB e Redis. Dopo deploy eseguire `scripts/aws/smoke.sh`, queue probe e `php artisan schedule:run`.

## Backup e restore

Eseguire `ENV_FILE=.env.production sh scripts/aws/backup-postgres.sh`; il dump custom viene validato e caricato cifrato in `s3://<bucket>/<prefix>`. Lifecycle: 14 giorni staging, 30 production; RDS conserva anche snapshot automatici 3/7 giorni. Per un drill creare manualmente un DB vuoto con suffisso `_restore_smoke`, esportare le cinque variabili `RESTORE_*` e lanciare `scripts/aws/restore-postgres.sh`. Lo script rifiuta database senza quel suffisso e usa `--exit-on-error`. Verificare migration e conteggi applicativi, poi eliminare manualmente soltanto il DB del drill.

## Deploy e rollback

Il workflow manuale pubblica immagini immutabili col tag SHA; il deploy via SSH recupera l'env da SSM, valida, migra e fa smoke. Lasciare `deploy=false` per produrre solo artefatti. Per rollback aggiornare in SSM i due image tag allo SHA precedente, rieseguire il deploy e non invertire automaticamente le migration.
