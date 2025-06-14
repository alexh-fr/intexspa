<?php
/**
 * Gestionnaire de timers pour le plugin IntexSpa
 */
class IntexSpaTimerHandler {
    
    private $eqLogic;
    private $cmd;
    private $timersFile;
    
    public function __construct($eqLogic, $cmd) {
        $this->eqLogic = $eqLogic;
        $this->cmd = $cmd;
        $this->timersFile = __DIR__ . '/../data/timers.json';
        
        // Créer le répertoire data s'il n'existe pas
        $dataDir = dirname($this->timersFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * Vérifie si c'est une commande timer
     */
    public static function isTimerCommand($logicalId) {
        // Patterns pour les commandes timer
        $timerPatterns = [
            '_timer_start',
            '_timer_stop', 
            '_timer_set_duration',
            '_timer_active',
            '_timer_duration_configured',
            '_timer_remaining_time'
        ];
        
        // Vérifier si c'est une commande de rafraîchissement
        if ($logicalId === 'refresh_timers') {
            return true;
        }
        
        // Vérifier si la commande contient un des patterns timer
        foreach ($timerPatterns as $pattern) {
            if (strpos($logicalId, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
/**
 * Exécute une commande de timer
 */
public function execute($_options = array()) {
    $logicalId = $this->cmd->getLogicalId();
    log::add('intexspa', 'debug', 'IntexSpaTimerHandler::execute() appelé avec logicalId: ' . $logicalId);
    
    // Gestion des commandes de timer
    if (strpos($logicalId, '_timer_start') !== false) {
        $equipment = str_replace('_timer_start', '', $logicalId);
        log::add('intexspa', 'debug', 'Détection commande START pour équipement: ' . $equipment);
        return $this->startTimer($equipment);
    } elseif (strpos($logicalId, '_timer_stop') !== false) {
        $equipment = str_replace('_timer_stop', '', $logicalId);
        log::add('intexspa', 'debug', 'Détection commande STOP pour équipement: ' . $equipment);
        return $this->stopTimer($equipment);
    } elseif (strpos($logicalId, '_timer_duration') !== false) {
        $equipment = str_replace('_timer_duration', '', $logicalId);
        log::add('intexspa', 'debug', 'Lecture durée actuelle pour équipement: ' . $equipment);
        return $this->getTimerDurationForDisplay($equipment);
    } elseif (strpos($logicalId, '_timer_set_duration') !== false) {
        $equipment = str_replace('_timer_set_duration', '', $logicalId);
        log::add('intexspa', 'debug', 'Définition nouvelle durée pour équipement: ' . $equipment);
        log::add('intexspa', 'debug', 'Options reçues: ' . json_encode($_options));
        
        $result = $this->setTimerDuration($equipment, $_options['slider']);
        
        $this->updateDurationInfoCommand($equipment);
        
        return $result; 
    } elseif ($logicalId === 'refresh_timers') {
        log::add('intexspa', 'debug', 'Détection commande de rafraîchissement des timers');
        return $this->refreshTimers();
    } else {
        log::add('intexspa', 'error', 'Commande timer non reconnue: ' . $logicalId);
        throw new Exception('Commande timer non reconnue: ' . $logicalId);
    }
}
    
    /**
     * Démarre un timer
     */
    private function startTimer($equipment) {
        $eqLogicId = $this->eqLogic->getId();
        
        // Valider l'équipement
        $this->validateEquipment($equipment);
        
        // Créer le dossier data s'il n'existe pas
        $this->ensureDataDirectory();
        
        // Charger les timers existants
        $timers = $this->loadTimers();
        
        $timerKey = $eqLogicId . '_' . $equipment;
        
        // Récupérer la durée configurée depuis la configuration de l'eqLogic
        $configKey = $equipment . '_duration';
        $duration = $this->eqLogic->getConfiguration($configKey, $this->getDefaultDuration($equipment));
        
        // Si pas de durée dans les timers existants, utiliser celle de la config
        if (isset($timers[$timerKey]['duration_configured'])) {
            $duration = $timers[$timerKey]['duration_configured'];
        }
        
        $duration = (int) $duration;

        // Créer/Mettre à jour le timer
        $timers[$timerKey] = [
            'eqLogic_id' => $eqLogicId,
            'equipment' => $equipment,
            'active' => true,
            'start_time' => time(),
            'duration' => $duration * 60, // Durée en secondes (minutes * 60)
            'end_time' => time() + ($duration * 60),
            'duration_configured' => $duration
        ];
        
        // Sauvegarder
        $this->saveTimers($timers);
        
        // Démarrer l'équipement correspondant
        $this->startEquipment($equipment);
        
        // Mettre à jour les commandes info
        $this->updateTimers();
        
        log::add('intexspa', 'info', 'Timer ' . $equipment . ' démarré pour ' . $duration . ' minute(s)');
        
        return true;
    }
    
    /**
     * Arrête un timer
     */
    private function stopTimer($equipment) {
        $eqLogicId = $this->eqLogic->getId();
        
        // Valider l'équipement
        $this->validateEquipment($equipment);
        
        if (!file_exists($this->timersFile)) {
            return true;
        }
        
        $timers = $this->loadTimers();
        if (!$timers) {
            return true;
        }
        
        $timerKey = $eqLogicId . '_' . $equipment;
        
        if (isset($timers[$timerKey])) {
            $timers[$timerKey]['active'] = false;
            $timers[$timerKey]['end_time'] = time();
            
            // Sauvegarder
            $this->saveTimers($timers);
            
            // Arrêter l'équipement correspondant
            $this->stopEquipment($equipment);
            
            // Mettre à jour les commandes info
            $this->updateTimers();
            
            log::add('intexspa', 'info', 'Timer ' . $equipment . ' arrêté');
        }
        
        return true;
    }
    
    /**
     * Définit la durée d'un timer
     */
    private function setTimerDuration($equipment, $duration) {
        // Valider l'équipement
        $this->validateEquipment($equipment);
        
        // Valider la durée selon l'équipement
        $limits = $this->getDurationLimits($equipment);
        if ($duration < $limits['min'] || $duration > $limits['max']) {
            throw new Exception('Durée invalide pour ' . $equipment . ' : ' . $duration . ' minutes (doit être entre ' . $limits['min'] . ' et ' . $limits['max'] . ' minutes)');
        }
        
        $eqLogicId = $this->eqLogic->getId();
        
        // Créer le dossier data s'il n'existe pas
        $this->ensureDataDirectory();
        
        // Charger les timers existants
        $timers = $this->loadTimers();
        
        $timerKey = $eqLogicId . '_' . $equipment;
        
        // Créer/Mettre à jour la configuration du timer
        if (!isset($timers[$timerKey])) {
            $timers[$timerKey] = [
                'eqLogic_id' => $eqLogicId,
                'equipment' => $equipment,
                'active' => false,
                'start_time' => 0,
                'duration' => $duration * 60, // En secondes
                'end_time' => 0,
                'duration_configured' => $duration
            ];
        } else {
            $timers[$timerKey]['duration_configured'] = $duration;
            $timers[$timerKey]['duration'] = $duration * 60; // En secondes
            
            // Si le timer est actif, recalculer l'heure de fin
            if ($timers[$timerKey]['active']) {
                $timers[$timerKey]['end_time'] = $timers[$timerKey]['start_time'] + ($duration * 60);
            }
        }
        
        // Sauvegarder dans la configuration de l'eqLogic aussi
        $this->eqLogic->setConfiguration($equipment . '_duration', $duration);
        $this->eqLogic->save();
        
        // Sauvegarder
        $this->saveTimers($timers);
        
        // Mettre à jour les commandes info
        $this->updateTimers();
        
        log::add('intexspa', 'info', 'Durée du timer ' . $equipment . ' définie à ' . $duration . ' minute(s)');
        
        return true;
    }
    
    /**
     * Retourne les durées par défaut selon l'équipement (en minutes)
     */
    private function getDefaultDuration($equipment) {
        $defaults = [
            'filtration' => 60,    // 1 heure
            'heater' => 60,       // 30 minutes  
            'sanitizer' => 60       // 5 minutes
        ];
        
        return isset($defaults[$equipment]) ? $defaults[$equipment] : 30;
    }
    
    /**
     * Retourne les limites de durée selon l'équipement (en minutes)
     */
    private function getDurationLimits($equipment) {
        $limits = [
            'filtration' => ['min' => 1, 'max' => 1440],    // 1 min à 24h
            'heater' => ['min' => 1, 'max' => 1440],       // 1 min à 24h
            'sanitizer' => ['min' => 1, 'max' => 1440]      // 1 min à 24h
        ];
        
        return isset($limits[$equipment]) ? $limits[$equipment] : ['min' => 1, 'max' => 60];
    }
    
    /**
     * Démarre un équipement via le gestionnaire de commandes principal
     */
    private function startEquipment($equipment) {
        try {
            $commandMapping = [
                'filtration' => 'filter_on',
                'heater' => 'heater_on',
                'sanitizer' => 'sanitizer_on'
            ];
            
            if (!isset($commandMapping[$equipment])) {
                throw new Exception('Équipement inconnu pour timer : ' . $equipment);
            }
            
            // Récupérer la commande correspondante
            $cmd = $this->eqLogic->getCmd(null, $commandMapping[$equipment]);
            if (!is_object($cmd)) {
                throw new Exception('Commande ' . $commandMapping[$equipment] . ' non trouvée');
            }
            
            // Exécuter la commande
            $cmd->execute();
            
            log::add('intexspa', 'debug', 'Équipement ' . $equipment . ' démarré via timer');
            return true;
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'Erreur lors du démarrage de ' . $equipment . ' : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Arrête un équipement via le gestionnaire de commandes principal
     */
    private function stopEquipment($equipment) {
        try {
            // Vérifications de base
            if (empty($equipment)) {
                log::add('intexspa', 'warning', 'stopEquipment: nom d\'équipement vide');
                return false;
            }
            
            if (!is_object($this->eqLogic)) {
                log::add('intexspa', 'error', 'stopEquipment: eqLogic invalide');
                return false;
            }
            
            $commandMapping = [
                'filtration' => 'filter_off',
                'heater' => 'heater_off',
                'sanitizer' => 'sanitizer_off'
            ];
            
            if (!isset($commandMapping[$equipment])) {
                log::add('intexspa', 'error', 'stopEquipment: Équipement inconnu pour timer : ' . $equipment);
                return false;
            }
            
            $commandId = $commandMapping[$equipment];
            log::add('intexspa', 'debug', "stopEquipment: Recherche de la commande {$commandId} pour {$equipment}");
            
            // Récupérer la commande correspondante avec vérification
            $cmd = $this->eqLogic->getCmd(null, $commandId);
            
            if (!is_object($cmd)) {
                log::add('intexspa', 'error', "stopEquipment: Commande {$commandId} non trouvée pour {$equipment}");
                
                // Debug: lister les commandes disponibles
                $allCmds = $this->eqLogic->getCmd();
                $availableCmds = [];
                if (is_array($allCmds)) {
                    foreach ($allCmds as $c) {
                        if (is_object($c)) {
                            $availableCmds[] = $c->getLogicalId();
                        }
                    }
                }
                log::add('intexspa', 'debug', "stopEquipment: Commandes disponibles: " . implode(', ', $availableCmds));
                
                return false;
            }
            
            // Vérifications supplémentaires de la commande
            if (method_exists($cmd, 'getIsEnable') && !$cmd->getIsEnable()) {
                log::add('intexspa', 'warning', "stopEquipment: La commande {$commandId} n'est pas activée");
                return false;
            }
            
            // Exécuter la commande
            log::add('intexspa', 'info', "stopEquipment: Exécution de {$commandId} pour arrêter {$equipment}");
            $cmd->execute();
            
            log::add('intexspa', 'info', "stopEquipment: Équipement {$equipment} arrêté avec succès via timer");
            return true;
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', "stopEquipement: Erreur lors de l'arrêt de {$equipment}: " . $e->getMessage());
            log::add('intexspa', 'error', "stopEquipement: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    
    /**
     * Rafraîchit les timers
     */
    private function refreshTimers() {
        $this->updateTimers();
        log::add('intexspa', 'debug', 'Timers rafraîchis');
        return true;
    }
    
    /**
     * Valide qu'un équipement est supporté
     */
    private function validateEquipment($equipment) {
        $validEquipments = ['filtration', 'heater', 'sanitizer'];
        if (!in_array($equipment, $validEquipments)) {
            throw new Exception('Équipement non supporté : ' . $equipment);
        }
    }
    
    /**
     * S'assure que le dossier data existe
     */
    private function ensureDataDirectory() {
        $dataDir = dirname($this->timersFile);
        if (!is_dir($dataDir)) {
            if (!mkdir($dataDir, 0755, true)) {
                throw new Exception('Impossible de créer le dossier data : ' . $dataDir);
            }
        }
    }
    
    /**
     * Charge les timers depuis le fichier JSON
     */
    private function loadTimers() {
        if (!file_exists($this->timersFile)) {
            return [];
        }
        
        $content = file_get_contents($this->timersFile);
        if ($content === false) {
            throw new Exception('Impossible de lire le fichier timers.json');
        }
        
        $timers = json_decode($content, true);
        if ($timers === null && json_last_error() !== JSON_ERROR_NONE) {
            log::add('intexspa', 'warning', 'Erreur de parsing JSON dans timers.json : ' . json_last_error_msg());
            return [];
        }
        
        return $timers ?: [];
    }
    
    /**
     * Sauvegarde les timers dans le fichier JSON
     */
    private function saveTimers($timers) {
        $json = json_encode($timers, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new Exception('Erreur lors de l\'encodage JSON des timers');
        }
        
        if (file_put_contents($this->timersFile, $json) === false) {
            throw new Exception('Impossible de sauvegarder le fichier timers.json');
        }
    }

    /**
     * Récupère la durée configurée d'un timer (en minutes pour l'affichage)
     */
    private function getTimerDurationForDisplay($equipment) {
        $durations = $this->loadTimerDurations();
        $durationMinutes = isset($durations[$equipment]) ? $durations[$equipment] : $this->getDefaultDuration($equipment);
        
        log::add('intexspa', 'debug', 'Durée récupérée pour affichage ' . $equipment . ': ' . $durationMinutes . ' minutes');
        return $durationMinutes;
    }

    /**
     * Récupère la durée configurée d'un timer (en secondes pour les calculs internes)
     */
    private function getTimerDuration($equipment) {
        $durations = $this->loadTimerDurations();
        $durationMinutes = isset($durations[$equipment]) ? $durations[$equipment] : $this->getDefaultDuration($equipment);
        
        log::add('intexspa', 'debug', 'Durée récupérée pour calcul ' . $equipment . ': ' . $durationMinutes . ' minutes');
        return $durationMinutes; // Retourne en minutes
    }

    /**
     * Charge les durées configurées des timers
     */
    private function loadTimerDurations() {
        $timers = $this->loadTimers();
        $durations = [];
        
        $eqLogicId = $this->eqLogic->getId();
        
        foreach ($timers as $timerKey => $timer) {
            if ($timer['eqLogic_id'] == $eqLogicId && isset($timer['duration_configured'])) {
                $equipment = $timer['equipment'];
                $durations[$equipment] = (int) $timer['duration_configured'];
            }
        }
        
        return $durations;
    }

    /**
     * Met à jour la commande info de durée après modification
     */
    private function updateDurationInfoCommand($equipment) {
        $durationCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_duration');
        if (is_object($durationCmd)) {
            $duration = $this->getTimerDurationForDisplay($equipment);
            $durationCmd->setCollectDate(date('Y-m-d H:i:s'));
            $durationCmd->event($duration);
            log::add('intexspa', 'debug', 'Commande info ' . $equipment . '_timer_duration mise à jour: ' . $duration . ' minutes');
        }
    }


    /**
     * Met à jour les informations des timers
     */
    public function updateTimers() {
        $timers = $this->loadTimers(); // Charge depuis JSON
        
        log::add('intexspa', 'debug', 'Mise à jour des timers pour eqLogic ID: ' . $this->eqLogic->getId());

        foreach ($timers as $timerKey => $timer) {
            if ($timer['eqLogic_id'] != $this->eqLogic->getId()) continue;
            
            $equipment = $timer['equipment'];
            
            $durationCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_duration');
            if (is_object($durationCmd)) {
                $durationMinutes = isset($timer['duration_configured']) 
                    ? $timer['duration_configured'] 
                    : ($timer['duration'] / 60);
                    
                $durationCmd->setCollectDate(date('Y-m-d H:i:s'));
                $durationCmd->event($durationMinutes);
                log::add('intexspa', 'debug', 'Timer ' . $equipment . ' durée mise à jour: ' . $durationMinutes . ' minutes');
            }
            
            // Pour les autres commandes (active, remaining time, etc.)
            $activeCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_active');
            if (is_object($activeCmd)) {
                $activeCmd->setCollectDate(date('Y-m-d H:i:s'));
                $activeCmd->event($timer['active'] ? 1 : 0);
            }
            
            // Temps restant (en minutes)
            if ($timer['active']) {
                $remainingSeconds = max(0, $timer['end_time'] - time());
                $remainingMinutes = round($remainingSeconds / 60);
                
                $remainingCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_remaining');
                if (is_object($remainingCmd)) {
                    $remainingCmd->setCollectDate(date('Y-m-d H:i:s'));
                    $remainingCmd->event($remainingMinutes);
                }
            }
        }
    }


    /**
     * Traite tous les timers actifs
     */
    public function processActiveTimers() {
        try {
            // Vérifications de base
            if (!is_object($this->eqLogic)) {
                log::add('intexspa', 'error', 'processActiveTimers: eqLogic invalide');
                return;
            }
            
            log::add('intexspa', 'debug', 'processActiveTimers: Début pour ' . $this->eqLogic->getName());
            
            $timers = $this->loadTimers();
            
            if (!is_array($timers)) {
                log::add('intexspa', 'warning', 'processActiveTimers: Aucun timer à traiter');
                return;
            }
            
            $currentTime = time();
            $hasChanged = false;
            $processedCount = 0;
            
            foreach ($timers as $timerKey => $timer) {
                try {
                    // Vérifications de sécurité
                    if (!is_array($timer)) {
                        log::add('intexspa', 'warning', "processActiveTimers: Timer invalide à l'index {$timerKey}");
                        continue;
                    }
                    
                    // Vérifier que ce timer appartient à cet équipement
                    if (!isset($timer['eqLogic_id']) || $timer['eqLogic_id'] != $this->eqLogic->getId()) {
                        continue;
                    }
                    
                    // Vérifier que le timer est actif
                    if (!isset($timer['active']) || !$timer['active']) {
                        continue;
                    }
                    
                    // Vérifier les champs obligatoires
                    if (!isset($timer['equipment']) || !isset($timer['end_time'])) {
                        log::add('intexspa', 'warning', "processActiveTimers: Timer {$timerKey} incomplet");
                        continue;
                    }
                    
                    $equipment = $timer['equipment'];
                    $endTime = intval($timer['end_time']);
                    $remainingSeconds = max(0, $endTime - $currentTime);
                    $remainingMinutes = round($remainingSeconds / 60);
                    
                    log::add('intexspa', 'debug', "processActiveTimers: Timer {$equipment} - reste {$remainingMinutes} minutes");
                    
                    // Mettre à jour le statut (même si pas expiré)
                    $this->updateTimerStatus($equipment, true, $remainingMinutes);
                    $processedCount++;
                    
                    // Vérifier si expiré
                    if ($remainingSeconds <= 0) {
                        log::add('intexspa', 'info', "processActiveTimers: Timer {$equipment} expiré");
                        
                        // Arrêter l'équipement de manière sécurisée
                        $stopResult = $this->stopEquipment($equipment);
                        
                        // Marquer le timer comme inactif
                        $timers[$timerKey]['active'] = false;
                        $timers[$timerKey]['end_time'] = $currentTime;
                        $timers[$timerKey]['stopped_at'] = date('Y-m-d H:i:s');
                        $hasChanged = true;
                        
                        // Mettre à jour les statuts
                        $this->updateTimerStatus($equipment, false, 0);
                        
                        $status = $stopResult ? 'avec succès' : 'avec erreur';
                        log::add('intexspa', 'info', "processActiveTimers: Timer {$equipment} traité {$status}");
                    }
                    
                } catch (Exception $e) {
                    log::add('intexspa', 'error', "processActiveTimers: Erreur traitement timer {$timerKey}: " . $e->getMessage());
                    continue; // Continuer avec les autres timers
                }
            }
            
            // Sauvegarder les changements
            if ($hasChanged) {
                $this->saveTimers($timers);
                log::add('intexspa', 'debug', 'processActiveTimers: Timers sauvegardés');
            }
            
            log::add('intexspa', 'debug', "processActiveTimers: Terminé - {$processedCount} timers traités");
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'processActiveTimers: Erreur générale: ' . $e->getMessage());
            log::add('intexspa', 'error', 'processActiveTimers: Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Met à jour les commandes de statut du timer
     */
    private function updateTimerStatus($equipment, $active, $remaining) {
        // Timer actif
        $activeCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_active');
        if (is_object($activeCmd)) {
            $activeCmd->setCollectDate(date('Y-m-d H:i:s'));
            $activeCmd->event($active ? 1 : 0);
        }
        
        // Temps restant
        $remainingCmd = $this->eqLogic->getCmd(null, $equipment . '_timer_remaining');
        if (is_object($remainingCmd)) {
            $remainingCmd->setCollectDate(date('Y-m-d H:i:s'));
            $remainingCmd->event($remaining);
        }
    }
    


    /**
     * Vérifie les états des équipements et les corrige si nécessaire
     * Appelée après refreshStatus pour avoir les derniers états
     */
    public function checkAndCorrectEquipmentStates() {
        try {
            if (!is_object($this->eqLogic)) {
                return;
            }
            
            log::add('intexspa', 'debug', 'checkAndCorrectEquipmentStates: Vérification pour ' . $this->eqLogic->getName());
            
            $timers = $this->loadTimers();
            if (!is_array($timers)) {
                log::add('intexspa', 'debug', 'checkAndCorrectEquipmentStates: Aucun timer trouvé');
                return;
            }
            
            $currentTime = time();
            $activeTimersCount = 0;
            $correctedCount = 0;
            
            foreach ($timers as $timer) {
                if (!is_array($timer) || 
                    !isset($timer['eqLogic_id']) || 
                    $timer['eqLogic_id'] != $this->eqLogic->getId() ||
                    !isset($timer['active']) || 
                    !$timer['active']) {
                    continue;
                }
                
                if (!isset($timer['equipment']) || !isset($timer['end_time'])) {
                    continue;
                }
                
                $equipment = $timer['equipment'];
                $endTime = intval($timer['end_time']);
                
                // Ne traiter que les timers encore actifs
                if ($currentTime >= $endTime) {
                    log::add('intexspa', 'debug', "checkAndCorrectEquipmentStates: Timer {$equipment} expiré, ignoré");
                    continue;
                }
                
                $activeTimersCount++;
                $remainingMinutes = round(($endTime - $currentTime) / 60);
                log::add('intexspa', 'debug', "checkAndCorrectEquipmentStates: Timer {$equipment} actif, reste {$remainingMinutes}mn");
                
                // Vérifier l'état actuel de l'équipement (après refresh)
                $isRunning = $this->isEquipmentRunning($equipment);
                
                if ($isRunning === false) {
                    // L'équipement est arrêté mais devrait tourner
                    log::add('intexspa', 'info', "checkAndCorrectEquipmentStates: {$equipment} arrêté (timer interne du spa?) - redémarrage nécessaire");
                    
                    if ($this->startEquipment($equipment)) {
                        $correctedCount++;
                        log::add('intexspa', 'info', "checkAndCorrectEquipmentStates: {$equipment} redémarré avec succès");
                        
                        // Re-vérifier après redémarrage
                        sleep(2);
                        $newState = $this->isEquipmentRunning($equipment);
                        if ($newState === true) {
                            log::add('intexspa', 'info', "checkAndCorrectEquipmentStates: Confirmation - {$equipment} fonctionne maintenant");
                        } else {
                            log::add('intexspa', 'warning', "checkAndCorrectEquipmentStates: {$equipment} redémarré mais état toujours incertain");
                        }
                    } else {
                        log::add('intexspa', 'warning', "checkAndCorrectEquipmentStates: Échec redémarrage {$equipment}");
                    }
                    
                } elseif ($isRunning === true) {
                    log::add('intexspa', 'debug', "checkAndCorrectEquipmentStates: {$equipment} fonctionne correctement");
                } else {
                    log::add('intexspa', 'debug', "checkAndCorrectEquipmentStates: État de {$equipment} indéterminé après refresh");
                }
            }
            
            // Résumé
            if ($activeTimersCount > 0) {
                $summary = "checkAndCorrectEquipmentStates: {$activeTimersCount} timers actifs";
                if ($correctedCount > 0) {
                    $summary .= ", {$correctedCount} équipements corrigés";
                    log::add('intexspa', 'info', $summary);
                } else {
                    $summary .= ", aucune correction nécessaire";
                    log::add('intexspa', 'debug', $summary);
                }
            } else {
                log::add('intexspa', 'debug', 'checkAndCorrectEquipmentStates: Aucun timer actif');
            }
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', 'checkAndCorrectEquipmentStates: ' . $e->getMessage());
            log::add('intexspa', 'error', 'checkAndCorrectEquipmentStates: Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Vérifie si un équipement est en marche
     * @param string $equipment
     * @return bool|null true=marche, false=arrêt, null=indéterminé
     */
    private function isEquipmentRunning($equipment) {
        try {
            $stateMapping = [
                'filtration' => 'filter_info',
                'heater' => 'heater_info', 
                'sanitizer' => 'sanitizer_info'
            ];
            
            if (!isset($stateMapping[$equipment])) {
                log::add('intexspa', 'warning', "isEquipmentRunning: Équipement inconnu: {$equipment}");
                return null;
            }
            
            $stateCmd = $this->eqLogic->getCmd('info', $stateMapping[$equipment]);
            if (!is_object($stateCmd)) {
                log::add('intexspa', 'debug', "isEquipmentRunning: Commande d'état {$stateMapping[$equipment]} non trouvée");
                return null;
            }
            
            $state = $stateCmd->execCmd();
            $isRunning = ($state == 1 || $state === true || $state === 'on');
            
            log::add('intexspa', 'debug', "isEquipmentRunning: {$equipment} état = {$state} (" . ($isRunning ? 'marche' : 'arrêt') . ")");
            
            return $isRunning;
            
        } catch (Exception $e) {
            log::add('intexspa', 'error', "isEquipmentRunning: Erreur pour {$equipment}: " . $e->getMessage());
            return null;
        }
    }

}
?>
