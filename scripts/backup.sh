#!/bin/bash

# =====================================================
# NexioSolution - Sistema di Backup e Restore
# =====================================================
# Script per backup incrementale di database e files
# con rotazione automatica e procedure di restore

# Configurazione
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${PROJECT_DIR}/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DATE=$(date +"%Y-%m-%d")

# Configurazione Database
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-nexiosolution}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# Configurazione Backup
RETENTION_DAYS="${RETENTION_DAYS:-7}"
BACKUP_TYPE="${1:-full}"  # full, incremental, differential
COMPRESS="${COMPRESS:-true}"
ENCRYPT="${ENCRYPT:-false}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"

# Configurazione S3 (opzionale)
S3_BUCKET="${S3_BUCKET:-}"
S3_REGION="${S3_REGION:-eu-west-1}"
AWS_PROFILE="${AWS_PROFILE:-default}"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni utility
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Crea directory di backup se non esiste
create_backup_dirs() {
    log "Creazione directory di backup..."

    mkdir -p "${BACKUP_DIR}/daily"
    mkdir -p "${BACKUP_DIR}/weekly"
    mkdir -p "${BACKUP_DIR}/monthly"
    mkdir -p "${BACKUP_DIR}/temp"
    mkdir -p "${BACKUP_DIR}/logs"
}

# Backup del database
backup_database() {
    local backup_file="${BACKUP_DIR}/temp/db_${DB_NAME}_${TIMESTAMP}.sql"

    log "Backup database ${DB_NAME}..."

    # Opzioni mysqldump ottimizzate
    mysqldump \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USER}" \
        --password="${DB_PASS}" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-table \
        --create-options \
        --extended-insert \
        --lock-tables=false \
        --quick \
        --set-charset \
        --column-statistics=0 \
        "${DB_NAME}" > "${backup_file}" 2>/dev/null

    if [ $? -eq 0 ]; then
        log "Database backup completato: ${backup_file}"

        # Comprimi se richiesto
        if [ "${COMPRESS}" = "true" ]; then
            gzip -9 "${backup_file}"
            backup_file="${backup_file}.gz"
            log "Database backup compresso"
        fi

        echo "${backup_file}"
    else
        error "Backup database fallito!"
        return 1
    fi
}

# Backup dei files
backup_files() {
    local backup_file="${BACKUP_DIR}/temp/files_${TIMESTAMP}.tar"

    log "Backup files applicazione..."

    # Lista directory da includere nel backup
    local include_dirs=(
        "backend/storage/app"
        "backend/config"
        "backend/.env"
        "frontend/.env"
        "docker"
        "scripts"
    )

    # Lista directory da escludere
    local exclude_dirs=(
        "*/node_modules"
        "*/vendor"
        "*.log"
        "*.cache"
        "*/temp"
        "*/tmp"
    )

    # Costruisci comando tar
    cd "${PROJECT_DIR}"

    # Crea stringa di esclusioni
    local exclude_args=""
    for exclude in "${exclude_dirs[@]}"; do
        exclude_args="${exclude_args} --exclude='${exclude}'"
    done

    # Esegui backup
    eval tar -cf "${backup_file}" ${exclude_args} "${include_dirs[@]}" 2>/dev/null

    if [ $? -eq 0 ]; then
        log "Files backup completato"

        # Comprimi se richiesto
        if [ "${COMPRESS}" = "true" ]; then
            gzip -9 "${backup_file}"
            backup_file="${backup_file}.gz"
            log "Files backup compresso"
        fi

        echo "${backup_file}"
    else
        error "Backup files fallito!"
        return 1
    fi
}

# Backup incrementale
backup_incremental() {
    local last_backup_file="${BACKUP_DIR}/last_backup.txt"
    local incremental_file="${BACKUP_DIR}/temp/incremental_${TIMESTAMP}.tar"

    log "Backup incrementale..."

    if [ ! -f "${last_backup_file}" ]; then
        warning "Nessun backup precedente trovato, eseguo backup completo"
        backup_full
        return
    fi

    local last_backup_date=$(cat "${last_backup_file}")

    cd "${PROJECT_DIR}"

    # Trova files modificati dall'ultimo backup
    find backend/storage/app backend/config -type f -newer "${last_backup_file}" -print0 | \
        tar -czf "${incremental_file}" --null -T - 2>/dev/null

    if [ $? -eq 0 ]; then
        log "Backup incrementale completato"
        echo "${incremental_file}"
    else
        error "Backup incrementale fallito!"
        return 1
    fi
}

# Backup completo
backup_full() {
    log "=== Avvio Backup Completo ==="

    create_backup_dirs

    # Backup database
    local db_backup=$(backup_database)
    if [ $? -ne 0 ]; then
        error "Backup fallito!"
        exit 1
    fi

    # Backup files
    local files_backup=$(backup_files)
    if [ $? -ne 0 ]; then
        error "Backup fallito!"
        exit 1
    fi

    # Crea archivio finale
    local final_backup="${BACKUP_DIR}/daily/backup_${DATE}_${TIMESTAMP}.tar"

    cd "${BACKUP_DIR}/temp"
    tar -cf "${final_backup}" $(basename "${db_backup}") $(basename "${files_backup}") 2>/dev/null

    if [ "${COMPRESS}" = "true" ]; then
        gzip -9 "${final_backup}"
        final_backup="${final_backup}.gz"
    fi

    # Crittografa se richiesto
    if [ "${ENCRYPT}" = "true" ] && [ -n "${ENCRYPTION_KEY}" ]; then
        openssl enc -aes-256-cbc -salt -in "${final_backup}" -out "${final_backup}.enc" -k "${ENCRYPTION_KEY}"
        rm "${final_backup}"
        final_backup="${final_backup}.enc"
    fi

    # Pulisci files temporanei
    rm -f "${BACKUP_DIR}/temp/"*

    # Aggiorna timestamp ultimo backup
    echo "${TIMESTAMP}" > "${BACKUP_DIR}/last_backup.txt"

    log "Backup completo salvato: ${final_backup}"

    # Upload su S3 se configurato
    if [ -n "${S3_BUCKET}" ]; then
        upload_to_s3 "${final_backup}"
    fi

    # Rotazione backup
    rotate_backups

    # Crea backup settimanale se è domenica
    if [ $(date +%u) -eq 7 ]; then
        cp "${final_backup}" "${BACKUP_DIR}/weekly/backup_week_$(date +%Y_%W).tar.gz"
        log "Backup settimanale creato"
    fi

    # Crea backup mensile se è il primo del mese
    if [ $(date +%d) -eq 01 ]; then
        cp "${final_backup}" "${BACKUP_DIR}/monthly/backup_month_$(date +%Y_%m).tar.gz"
        log "Backup mensile creato"
    fi

    log "=== Backup Completato con Successo ==="
}

# Upload su S3
upload_to_s3() {
    local file="$1"

    if ! command -v aws &> /dev/null; then
        warning "AWS CLI non installato, skip upload S3"
        return
    fi

    log "Upload backup su S3..."

    aws s3 cp "${file}" "s3://${S3_BUCKET}/nexiosolution/backups/$(basename ${file})" \
        --region "${S3_REGION}" \
        --profile "${AWS_PROFILE}"

    if [ $? -eq 0 ]; then
        log "Upload S3 completato"
    else
        warning "Upload S3 fallito"
    fi
}

# Rotazione backup
rotate_backups() {
    log "Rotazione backup vecchi..."

    # Rimuovi backup giornalieri più vecchi di RETENTION_DAYS
    find "${BACKUP_DIR}/daily" -name "backup_*.tar*" -type f -mtime +${RETENTION_DAYS} -delete

    # Rimuovi backup settimanali più vecchi di 4 settimane
    find "${BACKUP_DIR}/weekly" -name "backup_week_*.tar*" -type f -mtime +28 -delete

    # Rimuovi backup mensili più vecchi di 3 mesi
    find "${BACKUP_DIR}/monthly" -name "backup_month_*.tar*" -type f -mtime +90 -delete

    log "Rotazione completata"
}

# Restore database
restore_database() {
    local backup_file="$1"

    log "Restore database da ${backup_file}..."

    # Decomprimi se necessario
    if [[ "${backup_file}" == *.gz ]]; then
        gunzip -c "${backup_file}" | mysql \
            --host="${DB_HOST}" \
            --port="${DB_PORT}" \
            --user="${DB_USER}" \
            --password="${DB_PASS}" \
            "${DB_NAME}"
    else
        mysql \
            --host="${DB_HOST}" \
            --port="${DB_PORT}" \
            --user="${DB_USER}" \
            --password="${DB_PASS}" \
            "${DB_NAME}" < "${backup_file}"
    fi

    if [ $? -eq 0 ]; then
        log "Database restore completato"
    else
        error "Database restore fallito!"
        return 1
    fi
}

# Restore files
restore_files() {
    local backup_file="$1"

    log "Restore files da ${backup_file}..."

    cd "${PROJECT_DIR}"

    # Decomprimi e estrai
    if [[ "${backup_file}" == *.gz ]]; then
        tar -xzf "${backup_file}"
    else
        tar -xf "${backup_file}"
    fi

    if [ $? -eq 0 ]; then
        log "Files restore completato"
    else
        error "Files restore fallito!"
        return 1
    fi
}

# Restore completo
restore_full() {
    local backup_file="$1"

    if [ -z "${backup_file}" ]; then
        error "Specificare il file di backup da ripristinare"
        echo "Uso: $0 restore <backup_file>"
        exit 1
    fi

    if [ ! -f "${backup_file}" ]; then
        error "File di backup non trovato: ${backup_file}"
        exit 1
    fi

    log "=== Avvio Restore Completo ==="

    # Conferma
    read -p "ATTENZIONE: Questa operazione sovrascriverà tutti i dati attuali. Continuare? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "Restore annullato"
        exit 0
    fi

    # Crea backup di sicurezza prima del restore
    log "Creazione backup di sicurezza..."
    backup_full

    # Estrai archivio principale
    local temp_dir="${BACKUP_DIR}/restore_temp_${TIMESTAMP}"
    mkdir -p "${temp_dir}"

    # Decrittografa se necessario
    if [[ "${backup_file}" == *.enc ]]; then
        log "Decrittografia backup..."
        read -s -p "Inserisci password di decrittografia: " decrypt_key
        echo

        local decrypted_file="${temp_dir}/decrypted.tar.gz"
        openssl enc -aes-256-cbc -d -in "${backup_file}" -out "${decrypted_file}" -k "${decrypt_key}"
        backup_file="${decrypted_file}"
    fi

    cd "${temp_dir}"

    if [[ "${backup_file}" == *.gz ]]; then
        tar -xzf "${backup_file}"
    else
        tar -xf "${backup_file}"
    fi

    # Trova e ripristina database
    local db_file=$(find . -name "db_*.sql*" -type f | head -1)
    if [ -n "${db_file}" ]; then
        restore_database "${temp_dir}/${db_file}"
    fi

    # Trova e ripristina files
    local files_archive=$(find . -name "files_*.tar*" -type f | head -1)
    if [ -n "${files_archive}" ]; then
        restore_files "${temp_dir}/${files_archive}"
    fi

    # Pulisci
    rm -rf "${temp_dir}"

    # Clear cache Laravel
    cd "${PROJECT_DIR}/backend"
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear

    log "=== Restore Completato con Successo ==="
}

# Verifica backup
verify_backup() {
    local backup_file="$1"

    log "Verifica integrità backup ${backup_file}..."

    if [ ! -f "${backup_file}" ]; then
        error "File non trovato"
        return 1
    fi

    # Verifica archivio tar
    if [[ "${backup_file}" == *.gz ]]; then
        gzip -t "${backup_file}" 2>/dev/null
    else
        tar -tf "${backup_file}" > /dev/null 2>&1
    fi

    if [ $? -eq 0 ]; then
        log "Backup verificato con successo"

        # Mostra contenuto
        info "Contenuto backup:"
        if [[ "${backup_file}" == *.gz ]]; then
            tar -tzf "${backup_file}" | head -20
        else
            tar -tf "${backup_file}" | head -20
        fi
    else
        error "Backup corrotto o non valido!"
        return 1
    fi
}

# Lista backup disponibili
list_backups() {
    log "=== Backup Disponibili ==="

    echo -e "\n${BLUE}Backup Giornalieri:${NC}"
    ls -lh "${BACKUP_DIR}/daily/" 2>/dev/null | grep -E "backup_.*\.tar" | tail -5

    echo -e "\n${BLUE}Backup Settimanali:${NC}"
    ls -lh "${BACKUP_DIR}/weekly/" 2>/dev/null | grep -E "backup_week_.*\.tar"

    echo -e "\n${BLUE}Backup Mensili:${NC}"
    ls -lh "${BACKUP_DIR}/monthly/" 2>/dev/null | grep -E "backup_month_.*\.tar"

    # Mostra spazio utilizzato
    echo -e "\n${BLUE}Spazio utilizzato:${NC}"
    du -sh "${BACKUP_DIR}/"* 2>/dev/null
}

# Pulizia backup
cleanup_backups() {
    log "Pulizia backup obsoleti..."

    # Chiedi conferma
    read -p "Rimuovere tutti i backup più vecchi di ${RETENTION_DAYS} giorni? (y/n) " -n 1 -r
    echo

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        find "${BACKUP_DIR}" -name "backup_*.tar*" -type f -mtime +${RETENTION_DAYS} -delete
        log "Pulizia completata"
    else
        log "Pulizia annullata"
    fi
}

# Menu principale
show_menu() {
    echo -e "\n${BLUE}=== NexioSolution Backup Manager ===${NC}\n"
    echo "1) Backup Completo"
    echo "2) Backup Incrementale"
    echo "3) Backup Database"
    echo "4) Backup Files"
    echo "5) Restore Completo"
    echo "6) Lista Backup"
    echo "7) Verifica Backup"
    echo "8) Pulizia Backup"
    echo "9) Esci"
    echo
    read -p "Seleziona opzione: " choice

    case $choice in
        1) backup_full ;;
        2) backup_incremental ;;
        3) backup_database ;;
        4) backup_files ;;
        5)
            read -p "Inserisci path del backup da ripristinare: " backup_path
            restore_full "${backup_path}"
            ;;
        6) list_backups ;;
        7)
            read -p "Inserisci path del backup da verificare: " backup_path
            verify_backup "${backup_path}"
            ;;
        8) cleanup_backups ;;
        9) exit 0 ;;
        *) error "Opzione non valida" ;;
    esac
}

# Main
main() {
    # Verifica dipendenze
    for cmd in mysqldump mysql tar gzip; do
        if ! command -v $cmd &> /dev/null; then
            error "Comando $cmd non trovato. Installarlo prima di continuare."
            exit 1
        fi
    done

    # Parsing argomenti
    case "${1:-menu}" in
        full)
            backup_full
            ;;
        incremental)
            backup_incremental
            ;;
        database)
            backup_database
            ;;
        files)
            backup_files
            ;;
        restore)
            restore_full "$2"
            ;;
        list)
            list_backups
            ;;
        verify)
            verify_backup "$2"
            ;;
        cleanup)
            cleanup_backups
            ;;
        menu|*)
            while true; do
                show_menu
            done
            ;;
    esac
}

# Esegui
main "$@"