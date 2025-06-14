<style>
    .timer-container {
        padding: 15px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        border: 1px solid rgba(128, 128, 128, 0.1);
    }

    .timer-row {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        margin-bottom: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 6px;
        border: 1px solid rgba(128, 128, 128, 0.15);
        transition: all 0.2s ease;
    }

    .timer-row:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(128, 128, 128, 0.25);
    }

    .timer-row:last-child {
        margin-bottom: 0;
    }

    .timer-col-name {
        width: 120px;
        flex-shrink: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .timer-col-name i {
        width: 16px;
        text-align: center;
        opacity: 0.8;
    }

    .timer-col-status {
        width: 70px;
        flex-shrink: 0;
        text-align: center;
        font-size: 12px;
        font-weight: 500;
    }

    .timer-col-info {
        width: 85px;
        flex-shrink: 0;
        text-align: center;
        font-size: 12px;
        font-weight: 500;
        padding: 4px 8px;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 4px;
        margin: 0 5px;
    }

    .timer-col-controls {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .timer-col-controls input {
        width: 55px;
        text-align: center;
        font-size: 12px;
    }

    .status-active {
        color: #00a65a;
        font-weight: 600;
    }

    .status-inactive {
        color: #dd4b39;
        font-weight: 600;
    }

    .refresh-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 8px 12px;
        margin-bottom: 15px;
        font-size: 12px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 4px;
        border: 1px solid rgba(128, 128, 128, 0.1);
    }

    .refresh-bar label {
        margin: 0;
        font-weight: normal;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .refresh-bar select {
        width: 70px;
        font-size: 11px;
    }

    .timer-label {
        display: block;
        font-size: 10px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 2px;
        line-height: 1;
    }

    .timer-col-status,
    .timer-col-info {
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .timer-col-status span,
    .timer-col-info span {
        font-size: 13px;
        font-weight: 500;
    }
    .input-group-vertical {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-right: 10px;
    }

    .input-group-vertical .timer-label {
        margin-bottom: 3px;
        white-space: nowrap;
    }

    .input-group-vertical input {
        width: 55px;
    }

    .timer-col-controls {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .input-group-inline {
        display: flex;
        gap: 2px;
    }

    .input-group-inline input {
        width: 55px;
        border-radius: 4px 0 0 4px;
    }

    .input-group-inline .btn {
        border-radius: 0 4px 4px 0;
        padding: 4px 6px;
        font-size: 10px;
        line-height: 1;
    }
</style>

<!-- Barre de rafraîchissement -->
<div class="refresh-bar">
    <label>
        <input type="checkbox" id="autoRefreshEnabled"> 
        Auto-refresh
    </label>
    
    <label>
        <select id="refreshInterval">
            <option value="5000">5s</option>
            <option value="10000">10s</option>
            <option value="30000" selected>30s</option>
            <option value="60000">60s</option>
        </select>
    </label>
    
    <button class="btn btn-default btn-sm" onclick="IntexSpaTimerHandler.refreshTimerCommands()">
        <i class="fas fa-sync"></i> Rafraîchir
    </button>
</div>

<!-- Section Timers -->
<fieldset>
    <legend>{{Gestion des Timers}}</legend>
    
    <div class="timer-container">
        <!-- Timer Filtration -->
        <div class="timer-row">
            <div class="timer-col-name">
                <i class="fa fa-filter"></i> Filtration
            </div>
            <div class="timer-col-status">
                <small class="timer-label">Statut:</small>
                <span id="filtrationStatus" class="status-inactive">...</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Durée:</small>
                <span id="filtrationDuration">... min</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Restant:</small>
                <span id="filtrationRemaining">... min</span>
            </div>
        </div>
        
        <!-- Timer Chauffage -->
        <div class="timer-row">
            <div class="timer-col-name">
                <i class="fa fa-fire"></i> Chauffage
            </div>
            <div class="timer-col-status">
                <small class="timer-label">Statut:</small>
                <span id="heatingStatus" class="status-inactive">...</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Durée:</small>
                <span id="heatingDuration">... min</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Restant:</small>
                <span id="heatingRemaining">... min</span>
            </div>
        </div>
        
        <!-- Timer Désinfection -->
        <div class="timer-row">
            <div class="timer-col-name">
                <i class="fa fa-tint"></i> Désinfection
            </div>
            <div class="timer-col-status">
                <small class="timer-label">Statut:</small>
                <span id="sanitizerStatus" class="status-inactive">...</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Durée:</small>
                <span id="sanitizerDuration">... min</span>
            </div>
            <div class="timer-col-info">
                <small class="timer-label">Restant:</small>
                <span id="sanitizerRemaining">... min</span>
            </div>
        </div>
    </div>
</fieldset>

