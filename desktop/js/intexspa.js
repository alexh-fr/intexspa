/*
* Configuration spécifique pour l'équipement
*/

function validateEqLogicConfiguration() {
    // Validation du timeout
    var timeout = $('.eqLogicAttr[data-l2key="timeout"]').val();
    
    if (timeout && (parseInt(timeout) < 1 || parseInt(timeout) > 60)) {
        $('#div_alert').showAlert({
            message: 'Le timeout doit être compris entre 1 et 60 secondes',
            level: 'danger'
        });
        return false;
    }
    
    // Si pas de timeout défini, utiliser la valeur par défaut
    if (!timeout || timeout === '') {
        $('.eqLogicAttr[data-l2key="timeout"]').val(30);
    }
    
    return true;
}

// Événement lors de la sauvegarde
$('#bt_saveEqLogic').on('click', function (event) {
    if (!validateEqLogicConfiguration()) {
        event.preventDefault();
        return false;
    }
});

// Alternative plus robuste avec validation en temps réel
$('.eqLogicAttr[data-l2key="timeout"]').on('blur change', function() {
    var timeout = parseInt($(this).val());
    
    if (isNaN(timeout) || timeout < 1 || timeout > 60) {
        $(this).addClass('has-error');
        $(this).attr('title', 'Le timeout doit être compris entre 1 et 60 secondes');
        
        // Afficher un message d'erreur
        if (!$(this).next('.error-message').length) {
            $(this).after('<span class="error-message text-danger"><small>Valeur invalide (1-60s)</small></span>');
        }
    } else {
        $(this).removeClass('has-error');
        $(this).removeAttr('title');
        $(this).next('.error-message').remove();
    }
});

// -------------------------

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function sortCommandsByID() {
    var tbody = $('#table_cmd tbody');
    var rows = tbody.find('tr.cmd').get();
    
    rows.sort(function(a, b) {
        var aID = parseInt($(a).find('.cmdAttr[data-l1key="id"]').text()) || 0;
        var bID = parseInt($(b).find('.cmdAttr[data-l1key="id"]').text()) || 0;
        return aID - bID;
    });
    
    // Réinsérer les lignes triées
    $.each(rows, function(index, row) {
        tbody.append(row);
    });
}

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = { configuration: {} }
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {}
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
    tr += '<td class="hidden-xs">'
    tr += '<span class="cmdAttr" data-l1key="id"></span>'
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="' + init(_cmd.type) + '" style="display:none;" />';
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="subType" value="' + init(_cmd.subType) + '" style="display:none;" />';
    tr += '</td>'
    tr += '<td>'
    tr += '<div class="input-group">'
    tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
    tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
    tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
    tr += '</div>'
    tr += '</td>'
    tr += '<td>'
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
    tr += '</td>'
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += '</td>';
    tr += '<td>'
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
    tr += '</tr>'
    $('#table_cmd tbody').append(tr)
    var tr = $('#table_cmd tbody tr').last()

    tr.setValues(_cmd, '.cmdAttr')
    jeedom.cmd.changeType(tr, init(_cmd.subType))

    setTimeout(sortCommandsByID, 100); // Trier après ajout
}


$('.pluginAction[data-action=openLocation]').on('click', function () {
    window.open($(this).attr("data-location"), "_blank", null);
});

$('.eqLogicAction[data-action=copy]').on('click', function () {
    cloneEqLogic($(this).attr('data-eqLogic_id'), $(this).attr('data-name') + ' copie');
});

$('.cmdAction[data-action=copy]').on('click', function () {
    cloneCmd($(this).closest('.cmd').attr('data-cmd_id'), $(this).closest('.cmd').find('.cmdAttr[data-l1key=name]').val() + ' copie');
});
