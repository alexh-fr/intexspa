import asyncio
import json
import sys
from src import IntexSpa

# Récupérer les arguments depuis la ligne de commande
def get_arguments():
    if len(sys.argv) < 3:
        error_response = {
            "success": False,
            "return": {
                "error": "Arguments insuffisants",
                "usage": "python script.py <IP_SPA> <COMMANDE> [VALEUR]",
                "examples": [
                    "python script.py 192.168.1.194 status",
                    "python script.py 192.168.1.194 force_update",
                    "python script.py 192.168.1.194 power true",
                    "python script.py 192.168.1.194 power (inverse la valeur actuelle)",
                    "python script.py 192.168.1.194 preset_temp 35",
                    "python script.py 192.168.1.194 bubbles false",
                    "python script.py 192.168.1.194 heater (inverse la valeur actuelle)"
                ],
                "available_commands": [
                    "status - Récupère le statut du spa (avec force_update automatique)",
                    "force_update - Force l'actualisation des données du spa",
                    "power [true/false] - Active/désactive l'alimentation (inverse si pas de valeur)",
                    "filter [true/false] - Active/désactive le filtre (inverse si pas de valeur)",
                    "heater [true/false] - Active/désactive le chauffage (inverse si pas de valeur)",
                    "jets [true/false] - Active/désactive les jets (inverse si pas de valeur)",
                    "bubbles [true/false] - Active/désactive les bulles (inverse si pas de valeur)",
                    "sanitizer [true/false] - Active/désactive le sanitizer (inverse si pas de valeur)",
                    "preset_temp <nombre> - Définit la température de consigne (valeur obligatoire)"
                ]
            },
            "status": None
        }
        print(json.dumps(error_response, indent=2, ensure_ascii=False))
        sys.exit(1)
    
    spa_address = sys.argv[1]
    command = sys.argv[2]
    value = sys.argv[3] if len(sys.argv) > 3 else None
    
    return spa_address, command, value

def parse_value(value_str):
    """Convertit une chaîne en valeur appropriée (bool, int, etc.)"""
    if value_str is None:
        return None
    
    value_lower = value_str.lower()
    if value_lower in ['true', '1', 'on', 'yes']:
        return True
    elif value_lower in ['false', '0', 'off', 'no']:
        return False
    else:
        try:
            if '.' in value_str:
                return float(value_str)
            else:
                return int(value_str)
        except ValueError:
            return value_str

SPA_ADDRESS, COMMAND, VALUE = get_arguments()
spa = IntexSpa(SPA_ADDRESS)

# Fonctions de base pour communiquer avec le spa
async def get_spa_status():
    """Récupère le statut du spa"""
    return await spa.async_update_status()

async def set_spa_bubbles(state):
    return await spa.async_set_bubbles(state)

async def set_spa_filter(state):
    return await spa.async_set_filter(state)

async def set_spa_heater(state):
    return await spa.async_set_heater(state)

async def set_spa_jets(state):
    return await spa.async_set_jets(state)

async def set_spa_power(state):
    return await spa.async_set_power(state)

async def set_spa_sanitizer(state):
    return await spa.async_set_sanitizer(state)

async def set_spa_preset_temp(temp):
    return await spa.async_set_preset_temp(temp)

async def force_spa_update():
    """
    Force l'actualisation des données du spa en envoyant une température invalide.
    Contournement pour forcer le spa à actualiser ses données quand il chauffe.
    
    Returns:
        bool: True si le force_update a été effectué avec succès
    """
    try:
        # Envoyer une température invalide (106°C) pour forcer une erreur et l'actualisation
        await set_spa_preset_temp(41)
        # Attendre un peu pour que le spa traite la commande
        await asyncio.sleep(1)
        return True
    except Exception:
        return False

async def get_spa_status_dict(force_update=True):
    """
    Récupère le statut du spa et le convertit en dictionnaire.
    
    Args:
        force_update: Si True, force l'actualisation avant de récupérer le statut
    
    Returns:
        dict: Statut du spa ou None si erreur de communication
    """
    try:
        # Forcer l'actualisation si demandé
        if force_update:
            await force_spa_update()
        
        status = await get_spa_status()
        if status is None:
            return None
        
        return {
            "power": status.power,
            "filter": status.filter,
            "heater": status.heater,
            "jets": status.jets,
            "bubbles": status.bubbles,
            "sanitizer": status.sanitizer,
            "unit": status.unit,
            "current_temp": status.current_temp,
            "preset_temp": status.preset_temp,
            "error_code": status.error_code
        }
        
    except Exception as e:
        # Erreur de communication avec le spa
        return None

async def get_target_value(parameter, value, current_status):
    """
    Détermine la valeur cible en fonction du paramètre et de la valeur fournie.
    Si value est None pour les paramètres booléens, inverse la valeur actuelle.
    
    Args:
        parameter: nom du paramètre
        value: valeur fournie par l'utilisateur (peut être None)
        current_status: statut actuel du spa
        
    Returns:
        tuple: (target_value, is_toggle, previous_value)
    """
    # Paramètres qui acceptent le basculement
    toggleable_params = ["power", "filter", "heater", "jets", "bubbles", "sanitizer"]
    
    # Si une valeur est fournie, l'utiliser directement
    if value is not None:
        return value, False, None
    
    # Si pas de valeur et paramètre non basculable, erreur
    if parameter not in toggleable_params:
        return None, False, None
    
    # Récupérer la valeur actuelle pour basculer
    if current_status is None:
        return None, False, None
    
    current_value = current_status.get(parameter)
    if current_value is None:
        return None, False, None
    
    # Inverser la valeur booléenne
    target_value = not current_value
    return target_value, True, current_value

async def execute_spa_command(parameter, target_value):
    """
    Exécute une commande sur le spa.
    
    Args:
        parameter: nom du paramètre à modifier
        target_value: valeur cible
        
    Returns:
        bool: True si la commande a été envoyée avec succès
    """
    functions_map = {
        "power": set_spa_power,
        "filter": set_spa_filter,
        "heater": set_spa_heater,
        "jets": set_spa_jets,
        "bubbles": set_spa_bubbles,
        "sanitizer": set_spa_sanitizer,
        "preset_temp": set_spa_preset_temp
    }
    
    try:
        await functions_map[parameter](target_value)
        return True
    except Exception:
        return False

async def handle_status_command():
    """Traite la commande status avec force_update automatique"""
    status = await get_spa_status_dict(force_update=True)
    
    if status is None:
        return {
            "success": False,
            "return": {
                "command": "status",
                "requested": None,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": "Spa indisponible - Erreur de communication"
            },
            "status": None
        }
    
    return {
        "success": True,
        "return": {
            "command": "status",
            "requested": None,
            "current_value": None,
            "is_applied": "true",
            "is_error": "false" if status["error_code"] == 0 else "true",
            "note": "Statut récupéré avec succès" if status["error_code"] == 0 else f"Statut récupéré avec erreur (code: {status['error_code']})"
        },
        "status": status
    }

async def handle_force_update_command():
    """Traite la commande force_update"""
    # Effectuer le force_update
    update_success = await force_spa_update()
    
    if not update_success:
        return {
            "success": False,
            "return": {
                "command": "force_update",
                "requested": None,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": "Erreur lors du force_update - Spa indisponible"
            },
            "status": None
        }
    
    # Récupérer le statut après le force_update (sans refaire un force_update)
    status = await get_spa_status_dict(force_update=False)
    
    if status is None:
        return {
            "success": False,
            "return": {
                "command": "force_update",
                "requested": None,
                "current_value": None,
                "is_applied": "true",
                "is_error": "true",
                "note": "Force_update effectué mais impossible de récupérer le statut"
            },
            "status": None
        }
    
    return {
        "success": True,
        "return": {
            "command": "force_update",
            "requested": None,
            "current_value": None,
            "is_applied": "true",
            "is_error": "false" if status["error_code"] == 0 else "true",
            "note": "Force_update effectué et statut récupéré avec succès" if status["error_code"] == 0 else f"Force_update effectué, statut récupéré avec erreur (code: {status['error_code']})"
        },
        "status": status
    }

async def handle_action_command(parameter, value):
    """
    Traite une commande d'action (modification d'un paramètre).
    
    Args:
        parameter: nom du paramètre à modifier
        value: valeur demandée (peut être None pour toggle)
        
    Returns:
        dict: réponse formatée
    """
    # Mapping des paramètres valides
    valid_params = ["power", "filter", "heater", "jets", "bubbles", "sanitizer", "preset_temp"]
    
    if parameter not in valid_params:
        return {
            "success": False,
            "return": {
                "command": parameter,
                "requested": value,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": f"Commande '{parameter}' non reconnue"
            },
            "status": None
        }
    
    # Vérifier si preset_temp a une valeur
    if parameter == "preset_temp" and value is None:
        return {
            "success": False,
            "return": {
                "command": parameter,
                "requested": None,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": "La commande 'preset_temp' nécessite une valeur numérique"
            },
            "status": None
        }
    
    # Étape 1: Récupérer le statut initial (sans force_update pour éviter les interférences)
    initial_status = await get_spa_status_dict(force_update=False)
    
    if initial_status is None:
        return {
            "success": False,
            "return": {
                "command": parameter,
                "requested": value,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": "Spa indisponible - Erreur de communication"
            },
            "status": None
        }
    
    # Vérifier s'il y a une erreur au spa qui empêche les commandes
    if initial_status["error_code"] != 0:
        return {
            "success": True,  # Le spa répond mais refuse la commande
            "return": {
                "command": parameter,
                "requested": value,
                "current_value": initial_status.get(parameter),
                "is_applied": "false",
                "is_error": "true",
                "note": f"Commande refusée - Erreur spa (code: {initial_status['error_code']})"
            },
            "status": initial_status
        }
    
    # Étape 2: Déterminer la valeur cible
    target_value, is_toggle, previous_value = await get_target_value(parameter, value, initial_status)
    
    if target_value is None:
        return {
            "success": True,
            "return": {
                "command": parameter,
                "requested": value,
                "current_value": initial_status.get(parameter),
                "is_applied": "false",
                "is_error": "true",
                "note": f"Impossible de déterminer la valeur cible pour '{parameter}'"
            },
            "status": initial_status
        }
    
    # Si la valeur actuelle est déjà égale à la valeur cible
    current_value = initial_status.get(parameter)
    if current_value == target_value:
        note = "Valeur déjà correcte - Aucune action nécessaire"
        if is_toggle:
            note += f" (toggle demandé: {previous_value} -> {target_value})"
        
        return {
            "success": True,
            "return": {
                "command": parameter,
                "requested": target_value,
                "current_value": current_value,
                "is_applied": "true",
                "is_error": "false",
                "note": note
            },
            "status": initial_status
        }
    
    # Étape 3: Exécuter la commande
    command_sent = await execute_spa_command(parameter, target_value)
    
    if not command_sent:
        return {
            "success": False,
            "return": {
                "command": parameter,
                "requested": target_value,
                "current_value": current_value,
                "is_applied": "false",
                "is_error": "true",
                "note": "Erreur lors de l'envoi de la commande au spa"
            },
            "status": initial_status
        }
    
    # Étape 4: Attendre et vérifier le résultat
    await asyncio.sleep(2)  # Attendre que la commande soit prise en compte
    
    final_status = await get_spa_status_dict(force_update=False)
    
    if final_status is None:
        return {
            "success": False,
            "return": {
                "command": parameter,
                "requested": target_value,
                "current_value": current_value,
                "is_applied": "unknown",
                "is_error": "yes",
                "note": "Commande envoyée mais impossible de vérifier le résultat - Spa indisponible"
            },
            "status": None
        }
    
    # Vérifier si la commande a été appliquée
    new_value = final_status.get(parameter)
    is_applied = new_value == target_value
    
    # Vérifier s'il y a une nouvelle erreur
    has_new_error = final_status["error_code"] != 0
    
    # Construire la note
    if is_applied and not has_new_error:
        note = "Commande appliquée avec succès"
        if is_toggle:
            note += f" (toggle: {previous_value} -> {target_value})"
    elif is_applied and has_new_error:
        note = f"Commande appliquée mais erreur détectée (code: {final_status['error_code']})"
    elif not is_applied and has_new_error:
        note = f"Commande non appliquée - Erreur (code: {final_status['error_code']})"
    else:
        note = f"Commande non appliquée - Valeur actuelle: {new_value}, demandée: {target_value}"
    
    return {
        "success": True,
        "return": {
            "command": parameter,
            "requested": target_value,
            "current_value": new_value,
            "is_applied": "true" if is_applied else "false",
            "is_error": "true" if has_new_error else "false",
            "note": note
        },
        "status": final_status
    }

async def execute_command():
    """Fonction principale qui traite la commande"""
    
    if COMMAND == "status":
        return await handle_status_command()
    
    elif COMMAND == "force_update":
        return await handle_force_update_command()
    
    elif COMMAND in ["power", "filter", "heater", "jets", "bubbles", "sanitizer", "preset_temp"]:
        parsed_value = parse_value(VALUE) if VALUE is not None else None
        return await handle_action_command(COMMAND, parsed_value)
    
    else:
        return {
            "success": False,
            "return": {
                "command": COMMAND,
                "requested": VALUE,
                "current_value": None,
                "is_applied": "false",
                "is_error": "true",
                "note": f"Commande '{COMMAND}' non reconnue"
            },
            "status": None
        }

# Point d'entrée principal
if __name__ == "__main__":
    result = asyncio.run(execute_command())
    print(json.dumps(result, indent=2, ensure_ascii=False))