/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

var IntexSpaTimerHandler = {
    // Variables
    timerRefreshInterval: null,
    
    // Initialisation
    init: function() {
        //console.log('IntexSpaTimerHandler initialized');
        
        // Attendre que l'interface soit complètement chargée
        setTimeout(function() {
            IntexSpaTimerHandler.refreshTimerCommands();
        }, 2000);
        
        // Gestion du checkbox auto-refresh
        $('#autoRefreshEnabled').change(function() {
            if ($(this).is(':checked')) {
                IntexSpaTimerHandler.startAutoRefresh();
            } else {
                IntexSpaTimerHandler.stopAutoRefresh();
            }
        });
        
        // Gestion du changement d'intervalle
        $('#refreshInterval').change(function() {
            if ($('#autoRefreshEnabled').is(':checked')) {
                IntexSpaTimerHandler.startAutoRefresh();
            }
        });
    },

    // Gestion du rafraîchissement automatique
    startAutoRefresh: function() {
        //console.log('Starting auto refresh');
        IntexSpaTimerHandler.stopAutoRefresh();
        
        var interval = parseInt($('#refreshInterval').val()) || 30000;
        IntexSpaTimerHandler.timerRefreshInterval = setInterval(function() {
            IntexSpaTimerHandler.refreshTimerCommands();
        }, interval);
    },

    stopAutoRefresh: function() {
        if (IntexSpaTimerHandler.timerRefreshInterval) {
            clearInterval(IntexSpaTimerHandler.timerRefreshInterval);
            IntexSpaTimerHandler.timerRefreshInterval = null;
        }
    },

    // Récupération des informations timer
    refreshTimerCommands: function() {
        //console.log('refreshTimerCommands called');
        
        var eqId = $('.eqLogicAttr[data-l1key="id"]').val();
        //console.log('Equipment ID:', eqId);
        
        if (!eqId || eqId === '') {
            //console.log('Equipment ID not found');
            return;
        }
        
        $.ajax({
            type: 'POST',
            url: 'plugins/intexspa/core/ajax/intexspa.ajax.php',
            data: {
                action: 'getTimerCommands',
                eqLogic_id: eqId
            },
            dataType: 'json',
            timeout: 10000,
            error: function(request, status, error) {
                //console.log('AJAX Error:', status, error);
                IntexSpaTimerHandler.handleAjaxError(request, status, error);
            },
            success: function(data) {
                //console.log('AJAX Success:', data);
                if (data.state != 'ok') {
                    console.error('Server error:', data.result);
                    return;
                }
                IntexSpaTimerHandler.updateTimerDisplay(data.result);
            }
        });
    },

    // Mise à jour de l'affichage
    updateTimerDisplay: function(commands) {
        //console.log('updateTimerDisplay called with commands:', commands);
        
        // Réinitialiser les affichages
        var timers = {
            filtration: { duration: 0, remaining: 0, active: false },
            heating: { duration: 0, remaining: 0, active: false },
            sanitizer: { duration: 0, remaining: 0, active: false }
        };
        
        // Analyser les commandes pour extraire les informations des timers
        commands.forEach(function(cmd) {
            var logicalId = cmd.logicalId;
            var state = cmd.state;
            
            // Durées configurées
            if (logicalId === 'filtration_timer_duration') {
                timers.filtration.duration = parseInt(state) || 0;
            } else if (logicalId === 'heating_timer_duration') {
                timers.heating.duration = parseInt(state) || 0;
            } else if (logicalId === 'sanitizer_timer_duration') {
                timers.sanitizer.duration = parseInt(state) || 0;
            }
            
            // Temps restants
            else if (logicalId === 'filtration_timer_remaining') {
                timers.filtration.remaining = parseInt(state) || 0;
            } else if (logicalId === 'heating_timer_remaining') {
                timers.heating.remaining = parseInt(state) || 0;
            } else if (logicalId === 'sanitizer_timer_remaining') {
                timers.sanitizer.remaining = parseInt(state) || 0;
            }
            
            // États actifs
            else if (logicalId === 'filtration_timer_active') {
                timers.filtration.active = (state == 1 || state === true || state === 'true');
            } else if (logicalId === 'heating_timer_active') {
                timers.heating.active = (state == 1 || state === true || state === 'true');
            } else if (logicalId === 'sanitizer_timer_active') {
                timers.sanitizer.active = (state == 1 || state === true || state === 'true');
            }
        });
        
        //console.log('Extracted timer data:', timers);
        
        // Mettre à jour l'affichage pour chaque timer
        IntexSpaTimerHandler.updateSingleTimerDisplay('filtration', timers.filtration);
        IntexSpaTimerHandler.updateSingleTimerDisplay('heating', timers.heating);
        IntexSpaTimerHandler.updateSingleTimerDisplay('sanitizer', timers.sanitizer);
    },

    updateSingleTimerDisplay: function(timerType, timerData) {
        //console.log('Updating display for', timerType, 'with data:', timerData);
        
        // Mettre à jour le statut
        var statusElement = $('#' + timerType + 'Status');
        if (timerData.active) {
            statusElement.text('Actif').removeClass('status-inactive').addClass('status-active');
        } else {
            statusElement.text('Inactif').removeClass('status-active').addClass('status-inactive');
        }
        
        // Mettre à jour la durée configurée
        $('#' + timerType + 'Duration').text(timerData.duration + ' min');
        
        // Mettre à jour le temps restant
        var remainingElement = $('#' + timerType + 'Remaining');
        if (timerData.active && timerData.remaining > 0) {
            remainingElement.text(timerData.remaining + ' min');
        } else {
            remainingElement.text('0 min');
        }
        
        // **NOUVEAU** : Mettre à jour la valeur de l'input avec la durée configurée
        var durationInput = $('#' + timerType + 'DurationInput');
        if (timerData.duration > 0) {
            durationInput.val(timerData.duration);
        }
        
        // Mettre à jour les boutons
        var startBtn = $('#start' + IntexSpaTimerHandler.capitalizeFirst(timerType) + 'Timer');
        var stopBtn = $('#stop' + IntexSpaTimerHandler.capitalizeFirst(timerType) + 'Timer');
        
        if (timerData.active) {
            startBtn.prop('disabled', true);
            stopBtn.prop('disabled', false);
        } else {
            startBtn.prop('disabled', false);
            stopBtn.prop('disabled', true);
        }
    },


    // Actions des timers
    startTimer: function(timerType, duration) {
        //console.log('startTimer called for:', timerType, 'duration:', duration);
        
        var eqId = $('.eqLogicAttr[data-l1key="id"]').val();
        var durationValue = parseInt(duration);
        
        if (!durationValue || durationValue <= 0) {
            $('#div_alert').showAlert({
                message: 'Durée invalide',
                level: 'danger'
            });
            return;
        }
        
        var logicalId = timerType + '_timer_start';
        IntexSpaTimerHandler.executeTimerCommand(eqId, logicalId, {duration: durationValue});
    },

    stopTimer: function(timerType) {
        //console.log('stopTimer called for:', timerType);
        
        var eqId = $('.eqLogicAttr[data-l1key="id"]').val();
        var logicalId = timerType + '_timer_stop';
        
        IntexSpaTimerHandler.executeTimerCommand(eqId, logicalId, {});
    },

    executeTimerCommand: function(eqId, logicalId, options) {
        //console.log('executeTimerCommand:', eqId, logicalId, options);
        
        $.ajax({
            type: 'POST',
            url: 'plugins/intexspa/core/ajax/intexspa.ajax.php',
            data: {
                action: 'executeTimerCommand',
                eqLogic_id: eqId,
                logicalId: logicalId,
                options: JSON.stringify(options)
            },
            dataType: 'json',
            error: function(request, status, error) {
                //console.log('Timer command error:', status, error);
                IntexSpaTimerHandler.handleAjaxError(request, status, error);
            },
            success: function(data) {
                //console.log('Timer command success:', data);
                
                if (data.state !== 'ok') {
                    $('#div_alert').showAlert({
                        message: IntexSpaTimerHandler.cleanErrorMessage(data.result),
                        level: 'danger'
                    });
                    return;
                }
                
                $('#div_alert').showAlert({
                    message: 'Commande timer exécutée avec succès',
                    level: 'success'
                });
                
                setTimeout(IntexSpaTimerHandler.refreshTimerCommands, 1000);
            }
        });
    },

    // Utilitaires
    capitalizeFirst: function(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    },

    cleanErrorMessage: function(msg) {
        if (typeof msg === 'string') {
            return msg.replace(/<[^>]*>/g, '');
        }
        return msg;
    },

    handleAjaxError: function(request, status, error) {
        var message = 'Erreur AJAX: ' + status;
        if (request.responseText) {
            try {
                var response = JSON.parse(request.responseText);
                if (response.result) {
                    message = IntexSpaTimerHandler.cleanErrorMessage(response.result);
                }
            } catch (e) {
                message += ' - ' + IntexSpaTimerHandler.cleanErrorMessage(request.responseText.substring(0, 200));
            }
        }
        
        $('#div_alert').showAlert({
            message: message,
            level: 'danger'
        });
    },

    // Nettoyage à la fermeture
    cleanup: function() {
        IntexSpaTimerHandler.stopAutoRefresh();
    }
};

// Initialisation automatique
$(document).ready(function() {
    IntexSpaTimerHandler.init();
});

// Nettoyage à la fermeture
$(window).on('beforeunload', function() {
    IntexSpaTimerHandler.cleanup();
});
