<fieldset style="margin-top: 20px;">
    <div class="nocommand" style="">
        <div class="alert alert-warning" role="alert"> 
            <p>
                <i class="fas fa-info-circle"></i> Fonctionnalité en cours de développement.
                <br/> La programmation des actions de filtration, chauffage et désinfection est prévue pour la version 0.3.0.
            <P>
        </div>
    </div>
    <div class="row">
        <?php
            foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'] as $day) {
                echo '
                <div class="custom-col-7">
                    <div class="text-center"><h4>'.ucfirst($day).'</h4></div>
                    <div class="programbox text-center">
                        <span class="title">Filtration</span>
                        <form class="form-inline">
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="filtration" data-position="start"/>
                                    <div class="input-group-addon">à</div>
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="filtration" data-position="end"/>
                                </div>
                            </div>
                        </form>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" data-day="'.$day.'" data-type="filtration"> <span>Activer</span>
                            </label>
                        </div>
                    </div>
                    <div class="programbox text-center">
                        <span class="title">Chauffage</span>
                        <form class="form-inline">
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="heater" data-position="start" />
                                    <div class="input-group-addon">à</div>
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="heater" data-position="end"/>
                                </div>
                            </div>
                        </form>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" data-day="'.$day.'" data-type="heater"> <span>Activer</span>
                            </label>
                        </div>
                    </div>
                    <div class="programbox text-center">
                        <span class="title">Désinfection</span>
                        <form class="form-inline">
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="sanitizer" data-position="start"/>
                                    <div class="input-group-addon">à</div>
                                    <input type="time" class="form-control" placeholder="HH:MM" value="hh:ss" data-day="'.$day.'" data-type="sanitizer" data-position="end"/>
                                </div>
                            </div>
                        </form>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" data-day="'.$day.'" data-type="sanitizer"> <span>Activer</span>
                            </label>
                        </div>
                    </div>
                    <button class="btn btn-info btn-sm btn-block save-program" data-day="'.$day.'"><i class="fas fa-save"></i> {{Enregistrer}}</button>
                </div>
                ';
            }
        ?>
    </div>
</fieldset>

<style>
    .custom-col-7 {
        width: 14.1%;
        float: left;
        padding-left: 15px;
        padding-right: 15px;
        border: 1px solid grey;
        margin: 0px 1px;
        padding: 5px 0px;
    }

    @media (max-width: 768px) {
        .custom-col-7 {
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            border: 1px solid grey;
            margin: 0px 1px;
            margin-bottom: 10px;
            padding: 5px 0px;
        }
    }

    .programbox {
        border:  1px solid grey;
        padding: 10px 0px;
        margin: 10px 0px;
    }
    .programbox .title {
        font-size: large;
        font-weight: bold;
    }
</style>