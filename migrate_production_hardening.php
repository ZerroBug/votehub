<?php
declare(strict_types=1);
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/auth.php';
requireSuperAdmin();
$changes=[];
$exists=function(string $table,string $column)use($pdo):bool{$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");$s->execute([$table,$column]);return (bool)$s->fetchColumn();};
if(!$exists('ussd_sessions','network')){$pdo->exec("ALTER TABLE ussd_sessions ADD COLUMN network VARCHAR(40) NULL AFTER phone_number");$changes[]='Added network to ussd_sessions';}
if(!$exists('ussd_sessions','last_activity_at')){$pdo->exec("ALTER TABLE ussd_sessions ADD COLUMN last_activity_at DATETIME NULL AFTER ended_at");$changes[]='Added last_activity_at to ussd_sessions';}
$pdo->exec("CREATE TABLE IF NOT EXISTS webhook_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(190) NOT NULL UNIQUE,event_name VARCHAR(100) NOT NULL,payload JSON NULL,processed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(event_name),INDEX(processed_at)) ENGINE=InnoDB");
$pdo->exec("UPDATE ussd_sessions SET last_activity_at=COALESCE(last_activity_at,started_at) WHERE last_activity_at IS NULL");
require_once __DIR__.'/includes/functions.php';
flash('success',$changes?implode('; ',$changes).'. Webhook event table verified.':'Production hardening schema already up to date.');
redirect(APP_URL.'/admin/settings/');
