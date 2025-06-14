<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';

require_once __DIR__ . '/../../core/php/intexspaCommandHandler.inc.php';
require_once __DIR__ . '/../../core/php/intexspaTimerHandler.inc.php';

// ----------------------------------------

class intexspa extends eqLogic {

    /**
     * Exécuté toutes les minutes
     */
    public static function cron() {
        // Vérifier les timers de tous les équipements IntexSpa
        log::add('intexspa', 'debug', '------------------');
        log::add('intexspa', 'debug', 'Début cron - Vérification des timers IntexSpa');

        $eqLogics = eqLogic::byType('intexspa', true); // Seulement les actifs
        
        foreach ($eqLogics as $eqLogic) {
            try {
                // Créer le handler pour cet équipement
                $handler = new IntexSpaTimerHandler($eqLogic, null);
                
                // Traiter ses timers
                $handler->processActiveTimers();
                
            } catch (Exception $e) {
                log::add('intexspa', 'error', 'Erreur cron timer pour ' . $eqLogic->getName() . ': ' . $e->getMessage());
            }
        }

        log::add('intexspa', 'debug', 'Fin cron - Vérification des timers terminée');
        log::add('intexspa', 'debug', '------------------');
    }

    /**
     * Cron toutes les 5 minutes 
     */
    public static function cron5() {
        // Actualiser les statuts et vérifier les timers de tous les équipements
        log::add('intexspa', 'debug', '------------------');
        log::add('intexspa', 'debug', 'Début cron5 - Actualisation statuts et vérification timers');

        try {
            $eqLogics = eqLogic::byType('intexspa', true);
            
            foreach ($eqLogics as $eqLogic) {
                if (!$eqLogic->getIsEnable()) {
                    continue;
                }
                
                try {
                    log::add('intexspa', 'debug', 'cron5: Traitement de ' . $eqLogic->getName());
                    
                    // Exécuter la commande de rafraîchissement
                    $refreshCmd = $eqLogic->getCmd(null, 'refresh');
                    if (is_object($refreshCmd)) {
                        log::add('intexspa', 'debug', 'cron5: Exécution refresh pour ' . $eqLogic->getName());
                        
                        try {
                            $refreshResult = $refreshCmd->execute();
                            log::add('intexspa', 'debug', 'cron5: Refresh exécuté avec succès');
                        } catch (Exception $e) {
                            log::add('intexspa', 'warning', 'cron5: Erreur lors du refresh: ' . $e->getMessage());
                        }
                    } else {
                        log::add('intexspa', 'warning', 'cron5: Commande refresh non trouvée pour ' . $eqLogic->getName());
                    }
                    
                    // Vérifier et corriger les états des équipements selon les timers
                    $handler = new IntexSpaTimerHandler($eqLogic, null);
                    $handler->checkAndCorrectEquipmentStates();
                    
                } catch (Exception $e) {
                    log::add('intexspa', 'error', 'cron5: Erreur pour ' . $eqLogic->getName() . ': ' . $e->getMessage());
                }
            }            
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'cron5: Erreur globale: ' . $e->getMessage());
        }

        log::add('intexspa', 'debug', 'Fin cron5 - Actualisation et vérification terminées');
        log::add('intexspa', 'debug', '------------------');
    }



    /**
     * Exécuté toutes les 15 minutes
     */
    public static function cron15() {

    }

    /**
     * Exécuté tous les jours à minuit
     */
    public static function cronDaily() {
        log::add('intexspa', 'debug', '------------------');
        log::add('intexspa', 'info', 'Debut cronDaily - Maintenance quotidienne des timers');
    
        try {
            // Nettoyer les anciens logs de timers
            //TODO self::cleanExpiredTimers();
            
            // Vérifier la cohérence des données
            //TODO self::checkDataIntegrity();
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'Erreur maintenance quotidienne: ' . $e->getMessage());
        }

        log::add('intexspa', 'info', 'Fin cronDaily - Maintenance quotidienne terminée');
        log::add('intexspa', 'debug', '------------------');
    }

    /**
     * Exécuté toutes les heures
     */
    public static function cronHourly() {

    }


    // ----------------------------------------
    
    public function preInsert() {
        // Actions avant insertion
    }

    public function postInsert() {
        // Actions après insertion
    }

    public function preSave() {
        // Actions avant sauvegarde
    }

    public function postSave() {
        //parent::postSave();
        
        try {
            $commands = [
            // ===============================================
            // COMMANDES PRINCIPAL
            // ===============================================

                ['name' => 'Rafraîchir', 'logicalId' => 'refresh', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Statut', 'logicalId' => 'status', 'type' => 'info', 'subType' => 'string'],
                ['name' => 'Code erreur', 'logicalId' => 'error_code', 'type' => 'info', 'subType' => 'string'],

                ['name' => 'Alimentation', 'logicalId' => 'power_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Alimentation ON', 'logicalId' => 'power_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Alimentation OFF', 'logicalId' => 'power_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Alimentation toggle', 'logicalId' => 'power_toggle', 'type' => 'action', 'subType' => 'other'],

                ['name' => 'Température actuelle', 'logicalId' => 'current_temp', 'type' => 'info', 'subType' => 'numeric', 'unit' => '°C'],
                ['name' => 'Température consigne', 'logicalId' => 'preset_temp_info', 'type' => 'info', 'subType' => 'numeric', 'unit' => '°C'],
                ['name' => 'Définir température', 'logicalId' => 'set_preset_temp', 'type' => 'action', 'subType' => 'slider', 'configuration' => ['minValue' => 20, 'maxValue' => 40]],

                ['name' => 'Chauffage', 'logicalId' => 'heater_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Chauffage ON', 'logicalId' => 'heater_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Chauffage OFF', 'logicalId' => 'heater_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Chauffage toggle', 'logicalId' => 'heater_toggle', 'type' => 'action', 'subType' => 'other'],

                ['name' => 'Filtre', 'logicalId' => 'filter_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Filtre ON', 'logicalId' => 'filter_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Filtre OFF', 'logicalId' => 'filter_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Filtre toggle', 'logicalId' => 'filter_toggle', 'type' => 'action', 'subType' => 'other'],

                ['name' => 'Jets', 'logicalId' => 'jets_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Jets ON', 'logicalId' => 'jets_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Jets OFF', 'logicalId' => 'jets_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Jets toggle', 'logicalId' => 'jets_toggle', 'type' => 'action', 'subType' => 'other'],

                ['name' => 'Bulles', 'logicalId' => 'bubbles_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Bulles ON', 'logicalId' => 'bubbles_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Bulles OFF', 'logicalId' => 'bubbles_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Bulles toggle', 'logicalId' => 'bubbles_toggle', 'type' => 'action', 'subType' => 'other'],

                ['name' => 'Stérélisation', 'logicalId' => 'sanitizer_info', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Stérélisation ON', 'logicalId' => 'sanitizer_on', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Stérélisation OFF', 'logicalId' => 'sanitizer_off', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Stérélisation toggle', 'logicalId' => 'sanitizer_toggle', 'type' => 'action', 'subType' => 'other'],

            // ===============================================
            // COMMANDES TIMER
            // ===============================================

                // Commandes Timer Filtration
                ['name' => 'Timer Filtration - Actif', 'logicalId' => 'filtration_timer_active', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Timer Filtration - Durée configurée', 'logicalId' => 'filtration_timer_duration', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Filtration - Temps restant', 'logicalId' => 'filtration_timer_remaining', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Filtration - Démarrer', 'logicalId' => 'filtration_timer_start', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Filtration - Arrêter', 'logicalId' => 'filtration_timer_stop', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Filtration - Définir durée', 'logicalId' => 'filtration_timer_set_duration', 'type' => 'action', 'subType' => 'slider', 'configuration' => ['minValue' => 1, 'maxValue' => 1440, 'step' => 1]],

                // Commandes Timer Chauffage
                ['name' => 'Timer Chauffage - Actif', 'logicalId' => 'heater_timer_active', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Timer Chauffage - Durée configurée', 'logicalId' => 'heater_timer_duration', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Chauffage - Temps restant', 'logicalId' => 'heater_timer_remaining', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Chauffage - Démarrer', 'logicalId' => 'heater_timer_start', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Chauffage - Arrêter', 'logicalId' => 'heater_timer_stop', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Chauffage - Définir durée', 'logicalId' => 'heater_timer_set_duration', 'type' => 'action', 'subType' => 'slider', 'configuration' => ['minValue' => 1, 'maxValue' => 1440, 'step' => 1]],

                // Commandes Timer Désinfection
                ['name' => 'Timer Désinfection - Actif', 'logicalId' => 'sanitizer_timer_active', 'type' => 'info', 'subType' => 'binary'],
                ['name' => 'Timer Désinfection - Durée configurée', 'logicalId' => 'sanitizer_timer_duration', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Désinfection - Temps restant', 'logicalId' => 'sanitizer_timer_remaining', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min'],
                ['name' => 'Timer Désinfection - Démarrer', 'logicalId' => 'sanitizer_timer_start', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Désinfection - Arrêter', 'logicalId' => 'sanitizer_timer_stop', 'type' => 'action', 'subType' => 'other'],
                ['name' => 'Timer Désinfection - Définir durée', 'logicalId' => 'sanitizer_timer_set_duration', 'type' => 'action', 'subType' => 'slider', 'configuration' => ['minValue' => 1, 'maxValue' => 1440, 'step' => 1]],

                // Commande globale pour mettre à jour les timers
                ['name' => 'Rafraîchir Timers', 'logicalId' => 'refresh_timers', 'type' => 'action', 'subType' => 'other']
            ];
            
            foreach ($commands as $cmdConfig) {
                $cmd = $this->getCmd(null, $cmdConfig['logicalId']);
                if (!is_object($cmd)) {
                    $cmd = new intexspaCmd();
                    $cmd->setName($cmdConfig['name']);
                    $cmd->setLogicalId($cmdConfig['logicalId']);
                    $cmd->setEqLogic_id($this->getId());
                    $cmd->setType($cmdConfig['type']);
                    $cmd->setSubType($cmdConfig['subType']);
                    
                    if (isset($cmdConfig['unit'])) {
                        $cmd->setUnite($cmdConfig['unit']);
                    }
                    
                    if (isset($cmdConfig['configuration'])) {
                        foreach ($cmdConfig['configuration'] as $key => $value) {
                            $cmd->setConfiguration($key, $value);
                        }
                    }
                    
                    $cmd->save();
                }
            }

            log::add('intexspa', 'debug', 'Commandes créé avec succès');
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'Erreur dans postSave: ' . $e->getMessage());
        }
    }


    public function preUpdate() {
        // Actions avant mise à jour
    }

    public function postUpdate() {
        // Actions après mise à jour
    }

    public function preRemove() {
        // Actions avant suppression
    }

    public function postRemove() {
        // Actions après suppression
    }

    // ----------------------------------------
}

// ===============================================
// CLASSE DES COMMANDES
// ===============================================

class intexspaCmd extends cmd {
    
    public function execute($_options = array()) {
        log::add('intexspa', 'debug', '========== COMMANDE RECU ==========');

        $eqLogic = $this->getEqLogic();
        $logicalId = $this->getLogicalId();
        
        // Vérification des options pour les commandes standard
        $ip = $eqLogic->getConfiguration('ip');
        
        // Vérification de l'adresse IP
        if (empty($ip)) {
            throw new Exception('Adresse IP du spa non configurée');
        }
        // Validation de l'IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new Exception('Adresse IP invalide : ' . $ip);
        }
        log::add('intexspa', 'debug', 'IP valide : ' . $ip);
        
        // Vérification que l'équipement est actif
        if (!$eqLogic->getIsEnable()) {
            throw new Exception('L\'équipement est désactivé');
        }
        log::add('intexspa', 'debug', 'Equipement actif');

        // Chemin du script Python
        $scriptPath = __DIR__ . '/../../resources/intexspa-python/control.py';
        
        // Vérification de l'existence du script
        if (!file_exists($scriptPath)) {
            throw new Exception('Script Python non trouvé : ' . $scriptPath);
        }
        log::add('intexspa', 'debug', 'Script python trouvé : ' . $scriptPath);
        // ----


        log::add('intexspa', 'debug', 'Exécution commande: ' . $logicalId);
        // ===============================================
        // GESTION DES COMMANDES TIMER
        // ===============================================
        
        // Vérifier si c'est une commande timer
        if (IntexSpaTimerHandler::isTimerCommand($logicalId)) {
            if (class_exists('IntexSpaTimerHandler')) {
                log::add('intexspa', 'debug', 'Commande timer détectée: ' . $logicalId);
                $timerHandler = new IntexSpaTimerHandler($eqLogic, $this);
                return $timerHandler->execute($_options);
            } else {
                throw new Exception('Gestionnaire de timers non disponible');
            }
        }
        
        // ===============================================
        // GESTION DES COMMANDES STANDARD
        // ===============================================

        // Délégation gestionnaire de commandes standard
        log::add('intexspa', 'debug', 'Commande standard détectée: ' . $logicalId);
        if (class_exists('IntexSpaCommandHandler')) {
            $commandHandler = new IntexSpaCommandHandler($eqLogic, $this, $ip, $scriptPath);
            return $commandHandler->execute($_options);
        } else {
            throw new Exception('Gestionnaire de commandes non disponible');
        }

        log::add('intexspa', 'debug', '========== FIN COMMANDE RECU ==========');
    }
}
?>