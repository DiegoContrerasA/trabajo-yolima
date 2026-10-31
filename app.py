from flask import Flask, request, jsonify
import sqlite3
import os

app = Flask(__name__)

# Ruta de la base de datos SQLite
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.environ.get("DB_PATH", os.path.join(BASE_DIR, "FORMULARIO.db"))


# Configuración CORS para permitir la conexión con PHP
@app.after_request
def agregar_cors(respuesta):
    respuesta.headers["Access-Control-Allow-Origin"] = "http://localhost"
    respuesta.headers["Access-Control-Allow-Headers"] = "Content-Type"
    respuesta.headers["Access-Control-Allow-Methods"] = "POST, GET, OPTIONS"
    return respuesta


# Obtener conexión con SQLite
def obtener_conexion():
    conexion = sqlite3.connect(DB_PATH)
    conexion.row_factory = sqlite3.Row
    return conexion


# Crear la tabla si no existe
def crear_tabla():
    conexion = obtener_conexion()

    try:
        conexion.execute("""
            CREATE TABLE IF NOT EXISTS usuarios (
                cedula TEXT PRIMARY KEY,
                nombre1 TEXT NOT NULL,
                nombre2 TEXT,
                apellido1 TEXT NOT NULL,
                apellido2 TEXT,
                telefono TEXT,
                direccion TEXT
            )
        """)
        conexion.commit()
    finally:
        conexion.close()


# NUEVO: devuelve TODOS los registros (lo usa el botón de Excel)
@app.route("/listar", methods=["GET", "POST"])
def listar():
    conexion = None
    try:
        conexion = obtener_conexion()
        filas = conexion.execute(
            "SELECT cedula, nombre1, nombre2, apellido1, apellido2, "
            "telefono, direccion FROM usuarios ORDER BY apellido1, nombre1"
        ).fetchall()

        return jsonify({
            "mensaje": f"{len(filas)} registros encontrados.",
            "datos": [dict(f) for f in filas]
        })

    except sqlite3.Error as err:
        return jsonify({
            "mensaje": f"Error en SQLite: {err}",
            "datos": None
        }), 500

    finally:
        if conexion:
            conexion.close()


# Ruta para procesar las operaciones del formulario
@app.route("/procesar", methods=["POST", "OPTIONS"])
def procesar():

    # Responder a las solicitudes previas del navegador
    if request.method == "OPTIONS":
        return "", 204

    datos = request.get_json(silent=True)

    if not isinstance(datos, dict):
        return jsonify({
            "mensaje": "Debes enviar los datos en formato JSON.",
            "datos": None
        }), 400

    accion = datos.get("accion")
    cedula = datos.get("cedula")

    if accion not in ("guardar", "consultar", "editar", "eliminar"):
        return jsonify({
            "mensaje": "Acción no reconocida.",
            "datos": None
        }), 400

    if not isinstance(cedula, str) or not cedula.strip():
        return jsonify({
            "mensaje": "La cédula es obligatoria.",
            "datos": None
        }), 400

    cedula = cedula.strip()
    conexion = None

    try:
        conexion = obtener_conexion()
        cursor = conexion.cursor()

        # GUARDAR
        if accion == "guardar":

            nombre1 = datos.get("nombre1")
            apellido1 = datos.get("apellido1")

            if not isinstance(nombre1, str) or not nombre1.strip():
                return jsonify({
                    "mensaje": "El primer nombre es obligatorio.",
                    "datos": None
                }), 400

            if not isinstance(apellido1, str) or not apellido1.strip():
                return jsonify({
                    "mensaje": "El primer apellido es obligatorio.",
                    "datos": None
                }), 400

            sql = """
                INSERT INTO usuarios (
                    cedula, nombre1, nombre2, apellido1,
                    apellido2, telefono, direccion
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """

            valores = (
                cedula,
                nombre1.strip(),
                datos.get("nombre2"),
                apellido1.strip(),
                datos.get("apellido2"),
                datos.get("telefono"),
                datos.get("direccion")
            )

            cursor.execute(sql, valores)
            conexion.commit()

            mensaje = "Registro guardado exitosamente."
            resultado = None

        # CONSULTAR
        elif accion == "consultar":

            cursor.execute(
                "SELECT * FROM usuarios WHERE cedula = ?",
                (cedula,)
            )

            fila = cursor.fetchone()

            if fila:
                mensaje = "Usuario encontrado."
                resultado = dict(fila)
            else:
                mensaje = "Usuario no registrado."
                resultado = None

        # EDITAR
        elif accion == "editar":

            nombre1 = datos.get("nombre1")
            apellido1 = datos.get("apellido1")

            if not isinstance(nombre1, str) or not nombre1.strip():
                return jsonify({
                    "mensaje": "El primer nombre es obligatorio.",
                    "datos": None
                }), 400

            if not isinstance(apellido1, str) or not apellido1.strip():
                return jsonify({
                    "mensaje": "El primer apellido es obligatorio.",
                    "datos": None
                }), 400

            sql = """
                UPDATE usuarios
                SET nombre1 = ?,
                    nombre2 = ?,
                    apellido1 = ?,
                    apellido2 = ?,
                    telefono = ?,
                    direccion = ?
                WHERE cedula = ?
            """

            valores = (
                nombre1.strip(),
                datos.get("nombre2"),
                apellido1.strip(),
                datos.get("apellido2"),
                datos.get("telefono"),
                datos.get("direccion"),
                cedula
            )

            cursor.execute(sql, valores)
            conexion.commit()

            if cursor.rowcount > 0:
                mensaje = "Registro actualizado exitosamente."
            else:
                mensaje = "No se encontró un usuario con esa cédula."

            resultado = None

        # ELIMINAR
        else:

            cursor.execute(
                "DELETE FROM usuarios WHERE cedula = ?",
                (cedula,)
            )

            conexion.commit()

            if cursor.rowcount > 0:
                mensaje = "Registro eliminado exitosamente."
            else:
                mensaje = "No se encontró un usuario con esa cédula."

            resultado = None

        return jsonify({
            "mensaje": mensaje,
            "datos": resultado
        })

    except sqlite3.IntegrityError:
        if conexion:
            conexion.rollback()

        return jsonify({
            "mensaje": "La cédula ya existe o los datos incumplen una restricción.",
            "datos": None
        }), 409

    except sqlite3.Error as err:
        if conexion:
            conexion.rollback()

        return jsonify({
            "mensaje": f"Error en SQLite: {err}",
            "datos": None
        }), 500

    finally:
        if conexion:
            conexion.close()


# Crear la tabla al importar (gunicorn no ejecuta el bloque __main__)
crear_tabla()

# Iniciar el servidor en desarrollo local
if __name__ == "__main__":
    print("Base de datos lista.")
    print("Servidor Flask: http://127.0.0.1:5000")
    app.run(host="127.0.0.1", port=5000, debug=os.environ.get("FLASK_DEBUG") == "1")
