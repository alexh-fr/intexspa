<?php
    $plugin = plugin::byId('intexspa');
    sendVarToJS('eqType', $plugin->getId());
    $eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
    <!-- Partie gauche de la page -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoSecondary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span>{{Ajouter}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
        </div>
        
        <legend><i class="fas fa-table"></i> {{Mes Spa Intex}}</legend>
        <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Partie droite de la page -->
    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex">
            <span class="input-group-btn">
                <a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
                </a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
                </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
                </a>
            </span>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
            <li role="presentation"><a href="#infotab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-info-circle"></i> {{Informations}}</a></li>
            <li role="presentation"><a href="#actiontab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-play"></i> {{Commandes}}</a></li>
            <li role="presentation"><a href="#timertab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-clock"></i> {{Timer}}</a></li>
            <li role="presentation"><a href="#programtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-calendar-alt"></i> {{Programmation}}</a></li>
            <li role="presentation"><a href="#heattab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-fire"></i> {{Chauffage intelligent}}</a></li>
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-3">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Objet parent}}</label>
                                <div class="col-sm-3">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Catégorie}}</label>
                                <div class="col-sm-9">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label"></label>
                                <div class="col-sm-9">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Adresse IP du spa}}</label>
                                <div class="col-sm-3">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ip" placeholder="192.168.1.100"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Timeout (secondes)}}</label>
                                <div class="col-sm-3">
                                    <input type="number" class="eqLogicAttr form-control" 
                                        data-l1key="configuration" 
                                        data-l2key="timeout" 
                                        placeholder="30"
                                        min="1" 
                                        max="60" 
                                        step="1" />
                                </div>
                                <div class="col-sm-6">
                                    <span class="help-block">
                                        {{Délai d'attente pour les commandes (1-60 secondes)}}
                                    </span>
                                </div>
                            </div>
                            <div class="nocommand" style="">
                                <div class="alert alert-info" role="alert"> 
                                    <p>
                                        <i class="fas fa-info-circle"></i> L'exécution d'une commande prend généralement entre 1 à 5 secondes, mais peuvent mettre jusqu'à 60 secondes.
                                        <br/>Cela dépend principalement de la qualité de la connexion et si le spa est actif ou non.
                                        <br/>Si vous rencontrez des problèmes de commandes qui ne fonctionnent pas, essayez d'augmenter le délai d'attente ou consulter les logs.
                                    <P>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i> {{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Description}}</label>
                                <div class="col-sm-6">
                                    <textarea class="form-control eqLogicAttr autogrow" data-l1key="configuration" data-l2key="description"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>


            <div role="tabpanel" class="tab-pane" id="infotab">
                <?php require_once __DIR__ . '/info.tab.php'; ?>
            </div>

            <div role="tabpanel" class="tab-pane" id="actiontab">
                <?php require_once __DIR__ . '/action.tab.php'; ?>
            </div>


            <div role="tabpanel" class="tab-pane" id="timertab">
                <?php require_once __DIR__ . '/timer.tab.php'; ?>
            </div>

            <div role="tabpanel" class="tab-pane" id="programtab">               
                <?php require_once __DIR__ . '/program.tab.php'; ?>
            </div>

            <div role="tabpanel" class="tab-pane" id="heattab">
                <?php require_once __DIR__ . '/heat.tab.php'; ?>
            </div>

        </div>
    </div>
</div>

<?php include_file('desktop', 'intexspa', 'js', 'intexspa'); ?>
<?php include_file('desktop', 'intexspaTimerHandler', 'js', 'intexspa'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
