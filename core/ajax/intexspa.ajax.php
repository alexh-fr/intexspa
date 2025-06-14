<?php

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();
    
    log::add('intexspa', 'debug', 'AJAX Action: ' . init('action'));

    if (init('action') == 'getTimerCommands') {
        $eqLogic_id = init('eqLogic_id');
        
        log::add('intexspa', 'debug', 'getTimerCommands pour eqLogic_id: ' . $eqLogic_id);
        
        if ($eqLogic_id == '') {
            throw new Exception(__('EqLogic ID ne peut être vide', __FILE__));
        }
        
        $eqLogic = eqLogic::byId($eqLogic_id);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic non trouvé : ', __FILE__) . $eqLogic_id);
        }
        
        $commands = array();
        foreach ($eqLogic->getCmd() as $cmd) {
            try {
                // Ne pas exécuter les commandes qui peuvent causer des erreurs
                $state = null;
                if ($cmd->getType() == 'info') {
                    $state = $cmd->execCmd();
                }
                
                $commands[] = array(
                    'id' => $cmd->getId(),
                    'name' => $cmd->getName(),
                    'logicalId' => $cmd->getLogicalId(),
                    'type' => $cmd->getType(),
                    'subType' => $cmd->getSubType(),
                    'state' => $state
                );
            } catch (Exception $e) {
                log::add('intexspa', 'warning', 'Erreur lecture commande ' . $cmd->getName() . ': ' . $e->getMessage());
                $commands[] = array(
                    'id' => $cmd->getId(),
                    'name' => $cmd->getName(),
                    'logicalId' => $cmd->getLogicalId(),
                    'type' => $cmd->getType(),
                    'subType' => $cmd->getSubType(),
                    'state' => 'Erreur'
                );
            }
        }
        
        log::add('intexspa', 'debug', 'Commandes trouvées: ' . count($commands));
        
        ajax::success($commands);
    }

    if (init('action') == 'executeTimerCommand') {
        $eqLogic_id = init('eqLogic_id');
        $cmd_logicalId = init('cmd_logicalId');
        $options_json = init('options');
        
        log::add('intexspa', 'debug', 'executeTimerCommand: eqLogic=' . $eqLogic_id . ', cmd=' . $cmd_logicalId . ', options=' . $options_json);
        
        if ($eqLogic_id == '') {
            throw new Exception(__('EqLogic ID ne peut être vide', __FILE__));
        }
        
        if ($cmd_logicalId == '') {
            throw new Exception(__('LogicalId ne peut être vide', __FILE__));
        }
        
        $eqLogic = eqLogic::byId($eqLogic_id);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic non trouvé : ', __FILE__) . $eqLogic_id);
        }
        
        $cmd = $eqLogic->getCmd(null, $cmd_logicalId);
        if (!is_object($cmd)) {
            throw new Exception(__('Commande non trouvée : ', __FILE__) . $cmd_logicalId);
        }
        
        // Parser les options JSON
        $options = array();
        if (!empty($options_json)) {
            $options = json_decode($options_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Options JSON invalides: ' . json_last_error_msg());
            }
        }
        
        $result = $cmd->execCmd($options);
        log::add('intexspa', 'debug', 'Résultat commande timer: ' . json_encode($result));
        
        ajax::success($result);
    }

    if (init('action') == 'saveDuration') {
        $timerType = init('timerType');
        $duration = intval(init('duration'));
        
        // Validation
        $limits = [
            'filtration' => ['min' => 1, 'max' => 720],
            'heating' => ['min' => 1, 'max' => 480],
            'sanitizer' => ['min' => 1, 'max' => 120]
        ];
        
        if (!isset($limits[$timerType]) || $duration < $limits[$timerType]['min'] || $duration > $limits[$timerType]['max']) {
            throw new Exception('Durée invalide pour ' . $timerType);
        }
        
        // Sauvegarder dans la configuration
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifiez l\'ID', __FILE__));
        }
        
        $eqLogic->setConfiguration($timerType . '_duration', $duration);
        $eqLogic->save();
        
        ajax::success(['duration' => $duration, 'timerType' => $timerType]);
    }


    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    
} catch (Exception $e) {
    log::add('intexspa', 'error', 'Erreur AJAX: ' . $e->getMessage());
    ajax::error(displayException($e), $e->getCode());
}
?>
