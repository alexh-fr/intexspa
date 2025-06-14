<?php
/**
 * Gestionnaire de commandes pour le plugin IntexSpa
 */
class IntexSpaCommandHandler {
    
    private $eqLogic;
    private $cmd;
    private $ip;
    private $scriptPath;
    private $pythonCmd;
    
    /**
     * Constructeur - Initialise et valide l'environnement d'exécution
     */
    public function __construct($eqLogic, $cmd, $ip, $scriptPath) {
        $this->eqLogic = $eqLogic;
        $this->cmd = $cmd;
        $this->ip = $ip;
        $this->scriptPath = $scriptPath;
        
        // Déterminer et valider l'exécutable Python à l'initialisation
        $this->pythonCmd = $this->findAndValidatePythonExecutable();
    }
    
    /**
     * Trouve et valide l'exécutable Python approprié
     */
    private function findAndValidatePythonExecutable() {
        // Chemins possibles pour le Python du venv
        $possiblePaths = [
            __DIR__ . '/../resources/venv/bin/python3',
            __DIR__ . '/../resources/venv/bin/python',
            '/var/www/html/plugins/intexspa/resources/venv/bin/python3',
            '/usr/bin/python3'  // Fallback vers Python système
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                // Vérifier que Python fonctionne
                $testCmd = "{$path} --version 2>&1";
                $version = shell_exec($testCmd);
                if ($version !== null && strpos($version, 'Python') !== false) {
                    log::add('intexspa', 'debug', 'Python trouvé : ' . $path . ' (' . trim($version) . ')');
                    return $path;
                }
            }
        }
        
        // Test du Python système par défaut
        $testCmd = "python3 --version 2>&1";
        $version = shell_exec($testCmd);
        if ($version !== null && strpos($version, 'Python') !== false) {
            log::add('intexspa', 'debug', 'Python système utilisé : ' . trim($version));
            return 'python3';
        }
        
        throw new Exception('Aucun exécutable Python valide trouvé');
    }

    /**
     * Exécute une commande
     */
    public function execute($_options = array()) {
        $logicalId = $this->cmd->getLogicalId();
        $parts = explode('_', $logicalId);

        log::add('intexspa', 'debug', 'Commande reçu: ' . $logicalId);
        
        switch ($logicalId) {
            case 'refresh':
                return $this->refreshStatus();
                
            case 'set_preset_temp':
                return $this->setPresetTemperature($_options);
                
            default:
                return $this->executeDeviceCommand($parts);
        }
    }
    
    /**
     * Gère la commande de définition de température
     */
    private function setPresetTemperature($_options) {
        // Vérification de la présence de la température dans les options
        $temp = isset($_options['slider']) ? $_options['slider'] : null;
        if ($temp === null) {
            throw new Exception('Température non spécifiée');
        }
        log::add('intexspa', 'debug', 'Température définie : ' . $temp);
        
        // Validation de la température
        if ($temp < 20 || $temp > 40) {
            throw new Exception('Température hors limites (20-40°C) : ' . $temp);
        }
        log::add('intexspa', 'debug', 'Température valide : ' . $temp);

        return $this->executeCommand('preset_temp', $temp);
    }
    
    /**
     * Gère les commandes des appareils (power, heater, filter, etc.)
     */
    private function executeDeviceCommand($parts) {
        // Vérification du format de la commande
        if (count($parts) < 2) {
            throw new Exception('Commande non reconnue : ' . $this->cmd->getLogicalId());
        }
        log::add('intexspa', 'debug', 'Commande appareil : ' . $parts[0] . ' - Action : ' . $parts[1]);
        
        // Extraire la fonction et l'action
        $function = $parts[0]; // power, heater, filter, jets, bubbles, sanitizer
        $action = $parts[1];   // toggle, on, off
        
        // Validation de la fonction et de l'action
        $validFunctions = ['power', 'heater', 'filter', 'jets', 'bubbles', 'sanitizer'];
        
        // Vérification de la validité de la fonction
        if (!in_array($function, $validFunctions)) {
            throw new Exception('Fonction non valide : ' . $function);
        }
        log::add('intexspa', 'debug', 'Fonction valide : ' . $function);
        
        switch ($action) {
            case 'toggle':
                return $this->executeCommand($function);
            case 'on':
                return $this->executeCommand($function, 'on');
            case 'off':
                return $this->executeCommand($function, 'off');
            default:
                throw new Exception('Action non valide : ' . $action);
        }
    }
    
    /**
     * Rafraîchit le statut du spa
     */
    private function refreshStatus() {
        // Définir la commande pour le script Python
        $cmd = "{$this->pythonCmd} {$this->scriptPath} {$this->ip} status 2>&1";
        log::add('intexspa', 'debug', 'Exécution de la commande de statut : ' . $cmd);

        // Exécuter le script Python pour obtenir le statut
        $result = $this->executeWithTimeout($cmd, 20); // Timeout de 8 secondes

        // Vérifier si le résultat est valide
        if ($result === null || $result === false) {
            $this->setStatus(false); // Mettre à jour le statut du spa
            throw new Exception('Timeout ou erreur lors de l\'exécution du script de statut');
        }
        log::add('intexspa', 'debug', 'Résultat de la commande de statut : ' . $result);

        
        // Décoder le résultat JSON
        $data = json_decode($result, true);

        // Vérifier si le résultat contient une erreur
        if (!$data || !$data['success']) {
            $error = isset($data['return']) ? $data['return'] : 'Erreur inconnue lors du rafraîchissement';
            log::add('intexspa', 'error', 'Erreur lors de l\'exécution de ' . $command . ' : ' . $error);
            exit;
        }
        
        // Mettre à jour toutes les commandes info
        $this->updateInfoCommands($data['status']);
        
        return true;
    }
    
    /**
     * Exécute une commande sur le spa
     */
    private function executeCommand($command, $value = null) {
        // Définir la commande à exécuter
        $cmd = "{$this->pythonCmd} {$this->scriptPath} {$this->ip} {$command}";
        if ($value !== null) { $cmd .= " {$value}"; } // Ajouter la valeur si spécifiée
        $cmd .= " 2>&1";
        log::add('intexspa', 'debug', 'Exécution de la commande : ' . $cmd);
        
        // Exécuter la commande
        $result = $this->executeWithTimeout($cmd, 20); // Timeout de 10 secondes

        // Vérifier si le résultat est valide
        if ($result === null || $result === false) {
            $this->setStatus(false); // Mettre à jour le statut du spa
            throw new Exception('Timeout ou erreur lors de l\'exécution de la commande : ' . $command);
        }
        log::add('intexspa', 'debug', 'Résultat de la commande : ' . $result);
        
        // Décoder le résultat JSON
        $data = json_decode($result, true);

        // Vérifier si le résultat contient une erreur
        if (!$data || !$data['success']) {
            $error = isset($data['return']) ? $data['return'] : 'Erreur inconnue';
            log::add('intexspa', 'error', 'Erreur lors de l\'exécution de ' . $command . ' : ' . $error);
            exit;
        }
        
        // Rafraîchir le statut après une commande
        $this->updateInfoCommands($data['status']);
        
        return true;
    }
    
    /**
     * Met à jour les commandes info avec les nouvelles valeurs
     */
    private function updateInfoCommands($status) {
        // Définir les mappings entre le statut et les commandes info
        $mappings = [
            'current_temp' => $status['current_temp'],
            'preset_temp_info' => $status['preset_temp'],
            'power_info' => $status['power'] ? 1 : 0,
            'heater_info' => $status['heater'] ? 1 : 0,
            'filter_info' => $status['filter'] ? 1 : 0,
            'jets_info' => $status['jets'] ? 1 : 0,
            'bubbles_info' => $status['bubbles'] ? 1 : 0,
            'sanitizer_info' => $status['sanitizer'] ? 1 : 0,
            'error_code' => empty($status['error_code']) ? 'None' : $status['error_code']
        ];
        
        // Mettre à jour les commandes info avec les valeurs du statut
        foreach ($mappings as $cmdId => $value) {
            $cmd = $this->eqLogic->getCmd('info', $cmdId);
            if (is_object($cmd)) {
                log::add('intexspa', 'debug', 'Mise à jour de la commande info : ' . $cmdId . ' avec la valeur : ' . $value);
                $cmd->setCollectDate('');
                $cmd->event($value); 
            }
        }

        $this->setStatus(true); // Mettre à jour le statut du spa
        
        // Log de mise à jour
        log::add('intexspa', 'debug', 'Statut mis à jour pour ' . $this->eqLogic->getName());
    }

    /**
     * Exécute une commande avec timeout
     */
    private function executeWithTimeout($cmd, $timeout = 10) {
        $startTime = microtime(true);
        log::add('intexspa', 'debug', 'Démarrage de la commande avec timeout de ' . $timeout . 's');
        
        // Créer un processus avec proc_open pour pouvoir gérer le timeout
        $descriptors = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open($cmd, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            log::add('intexspa', 'error', 'Impossible de créer le processus');
            return false;
        }
        
        // Fermer stdin car on n'en a pas besoin
        fclose($pipes[0]);
        
        // Rendre les pipes non-bloquants
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        
        $output = '';
        $error = '';
        
        // Boucle de lecture avec timeout
        while (true) {
            $currentTime = microtime(true);
            $elapsedTime = $currentTime - $startTime;
            
            // Vérifier le timeout
            if ($elapsedTime >= $timeout) {
                log::add('intexspa', 'warning', 'Timeout atteint (' . $timeout . 's) - Arrêt forcé du processus');
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return false;
            }
            
            // Vérifier le statut du processus
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Le processus s'est terminé normalement
                break;
            }
            
            // Lire les données disponibles
            $output .= stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);
            
            // Petite pause pour éviter de consommer trop de CPU
            usleep(100000); // 0.1 seconde
        }
        
        // Lire les dernières données
        $output .= stream_get_contents($pipes[1]);
        $error .= stream_get_contents($pipes[2]);
        
        // Fermer les pipes
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        // Obtenir le code de retour
        $returnCode = proc_close($process);
        
        $executionTime = microtime(true) - $startTime;
        log::add('intexspa', 'debug', 'Commande terminée en ' . round($executionTime, 2) . 's');
        
        // Si il y a une erreur et pas de sortie, retourner l'erreur
        if ($returnCode !== 0 && empty($output) && !empty($error)) {
            log::add('intexspa', 'error', 'Erreur d\'exécution: ' . $error);
            return false;
        }
        
        return !empty($output) ? $output : $error;
    }

    /**
     * Met à jour le statut du spa
     */
    private function setStatus($status) {
        // Vérifier si le statut est un booléen
        if (!is_bool($status)) {
            throw new Exception('Statut doit être un booléen (true/false)');
        }

        // Réciupérer la commande de statut	
        $cmd = $this->eqLogic->getCmd('info', 'status');
        
        // Vérifier si la commande existe
        if (!is_object($cmd)) {
            throw new Exception('Commande de statut non trouvée');
        }

        // Mettre à jour la commande de statut
        $cmd->setCollectDate('');

        // Définir l'événement en fonction du statut
        switch ($status) {
            case true:
                $cmd->event(1);
                break;
            
            default:
                $cmd->event(0);
                break;
        }
        
        // Log de mise à jour
        log::add('intexspa', 'debug', 'Statut mis à jour : ' . $status);
    }

}

?>