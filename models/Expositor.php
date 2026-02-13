<?php

require_once __DIR__ . '/../config/database.php';

class Expositor
{
    private $conn;
    private $table_name = "expositores";

    public $id;
    public $nombre;
    public $apellido;
    public $correo;
    public $telefono;
    public $razon_social;
    public $cargo_contacto;
    public $giro_empresa;
    public $logo_ruta;
    public $mampara;
    public $rotulo_antepecho;
    public $hoja_responsiva_ruta;
    public $created_at;
    public $updated_at;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }

    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      nombre=:nombre,
                      apellido=:apellido,
                      correo=:correo,
                      telefono=:telefono,
                      razon_social=:razon_social,
                      cargo_contacto=:cargo_contacto,
                      giro_empresa=:giro_empresa,
                      logo_ruta=:logo_ruta,
                      mampara=:mampara,
                      rotulo_antepecho=:rotulo_antepecho,
                      hoja_responsiva_ruta=:hoja_responsiva_ruta,
                      created_at=CURRENT_TIMESTAMP,
                      updated_at=CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->apellido = htmlspecialchars(strip_tags($this->apellido));
        $this->correo = htmlspecialchars(strip_tags($this->correo));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->razon_social = htmlspecialchars(strip_tags($this->razon_social));
        $this->cargo_contacto = htmlspecialchars(strip_tags($this->cargo_contacto));
        $this->giro_empresa = htmlspecialchars(strip_tags($this->giro_empresa));
        $this->logo_ruta = htmlspecialchars(strip_tags($this->logo_ruta));
        // Ensure mampara is an integer (0 or 1)
        $this->mampara = (int) filter_var($this->mampara, FILTER_VALIDATE_BOOLEAN);
        $this->rotulo_antepecho = htmlspecialchars(strip_tags($this->rotulo_antepecho));
        $this->hoja_responsiva_ruta = htmlspecialchars(strip_tags($this->hoja_responsiva_ruta));

        // bind values
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":apellido", $this->apellido);
        $stmt->bindParam(":correo", $this->correo);
        $stmt->bindParam(":telefono", $this->telefono);
        $stmt->bindParam(":razon_social", $this->razon_social);
        $stmt->bindParam(":cargo_contacto", $this->cargo_contacto);
        $stmt->bindParam(":giro_empresa", $this->giro_empresa);
        $stmt->bindParam(":logo_ruta", $this->logo_ruta);
        $stmt->bindParam(":mampara", $this->mampara, PDO::PARAM_INT);
        $stmt->bindParam(":rotulo_antepecho", $this->rotulo_antepecho);
        $stmt->bindParam(":hoja_responsiva_ruta", $this->hoja_responsiva_ruta);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errorCode = $stmt->errorCode();
            error_log("Error al crear expositor: " . $this->error);
        }

        return false;
    }

    public $error;
    public $errorCode;

    public function readOne()
    {
        $query = "SELECT
                    id, nombre, apellido, correo, telefono, razon_social, cargo_contacto,
                    giro_empresa, logo_ruta, mampara, rotulo_antepecho, hoja_responsiva_ruta,
                    created_at, updated_at
                FROM
                    " . $this->table_name . "
                WHERE
                    id = ?
                LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->nombre = $row['nombre'];
            $this->apellido = $row['apellido'];
            $this->correo = $row['correo'];
            $this->telefono = $row['telefono'];
            $this->razon_social = $row['razon_social'];
            $this->cargo_contacto = $row['cargo_contacto'];
            $this->giro_empresa = $row['giro_empresa'];
            $this->logo_ruta = $row['logo_ruta'];
            $this->mampara = (bool) $row['mampara']; // Cast to boolean when reading
            $this->rotulo_antepecho = $row['rotulo_antepecho'];
            $this->hoja_responsiva_ruta = $row['hoja_responsiva_ruta'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    public function update()
    {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      nombre=:nombre,
                      apellido=:apellido,
                      correo=:correo,
                      telefono=:telefono,
                      razon_social=:razon_social,
                      cargo_contacto=:cargo_contacto,
                      giro_empresa=:giro_empresa,
                      logo_ruta=:logo_ruta,
                      mampara=:mampara,
                      rotulo_antepecho=:rotulo_antepecho,
                      hoja_responsiva_ruta=:hoja_responsiva_ruta,
                      updated_at=CURRENT_TIMESTAMP
                  WHERE
                      id = :id";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->apellido = htmlspecialchars(strip_tags($this->apellido));
        $this->correo = htmlspecialchars(strip_tags($this->correo));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->razon_social = htmlspecialchars(strip_tags($this->razon_social));
        $this->cargo_contacto = htmlspecialchars(strip_tags($this->cargo_contacto));
        $this->giro_empresa = htmlspecialchars(strip_tags($this->giro_empresa));
        $this->logo_ruta = htmlspecialchars(strip_tags($this->logo_ruta));
        // Ensure mampara is an integer (0 or 1)
        $this->mampara = (int) filter_var($this->mampara, FILTER_VALIDATE_BOOLEAN);
        $this->rotulo_antepecho = htmlspecialchars(strip_tags($this->rotulo_antepecho));
        $this->hoja_responsiva_ruta = htmlspecialchars(strip_tags($this->hoja_responsiva_ruta));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // bind values
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":apellido", $this->apellido);
        $stmt->bindParam(":correo", $this->correo);
        $stmt->bindParam(":telefono", $this->telefono);
        $stmt->bindParam(":razon_social", $this->razon_social);
        $stmt->bindParam(":cargo_contacto", $this->cargo_contacto);
        $stmt->bindParam(":giro_empresa", $this->giro_empresa);
        $stmt->bindParam(":logo_ruta", $this->logo_ruta);
        $stmt->bindParam(":mampara", $this->mampara, PDO::PARAM_INT);
        $stmt->bindParam(":rotulo_antepecho", $this->rotulo_antepecho);
        $stmt->bindParam(":hoja_responsiva_ruta", $this->hoja_responsiva_ruta);
        $stmt->bindParam(':id', $this->id);

        try {
            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar expositor con ID " . $this->id . ": " . $e->getMessage());
        }

        return false;
    }

    public function delete()
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }
}
