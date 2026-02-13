<?php

require_once __DIR__ . '/../config/database.php';

class Participante
{
    private $conn;
    private $table_name = "participantes";

    public $id;
    public $expositor_id;
    public $nombre_completo;
    public $cargo_puesto;
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
                      expositor_id=:expositor_id,
                      nombre_completo=:nombre_completo,
                      cargo_puesto=:cargo_puesto,
                      created_at=CURRENT_TIMESTAMP,
                      updated_at=CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->expositor_id = htmlspecialchars(strip_tags($this->expositor_id));
        $this->nombre_completo = htmlspecialchars(strip_tags($this->nombre_completo));
        $this->cargo_puesto = htmlspecialchars(strip_tags($this->cargo_puesto));

        // bind values
        $stmt->bindParam(":expositor_id", $this->expositor_id, PDO::PARAM_INT);
        $stmt->bindParam(":nombre_completo", $this->nombre_completo);
        $stmt->bindParam(":cargo_puesto", $this->cargo_puesto);

        try {
            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Error al crear participante: " . $this->error);
        }

        return false;
    }

    public $error;

    public function read()
    {
        $query = "SELECT
                    id, expositor_id, nombre_completo, cargo_puesto,
                    created_at, updated_at
                FROM
                    " . $this->table_name . "
                WHERE
                    expositor_id = ?
                ORDER BY
                    created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->expositor_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function readOne()
    {
        $query = "SELECT
                    id, expositor_id, nombre_completo, cargo_puesto,
                    created_at, updated_at
                FROM
                    " . $this->table_name . "
                WHERE
                    id = ?
                LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->expositor_id = $row['expositor_id'];
            $this->nombre_completo = $row['nombre_completo'];
            $this->cargo_puesto = $row['cargo_puesto'];
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
                      nombre_completo=:nombre_completo,
                      cargo_puesto=:cargo_puesto,
                      updated_at=CURRENT_TIMESTAMP
                  WHERE
                      id = :id";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->nombre_completo = htmlspecialchars(strip_tags($this->nombre_completo));
        $this->cargo_puesto = htmlspecialchars(strip_tags($this->cargo_puesto));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // bind values
        $stmt->bindParam(":nombre_completo", $this->nombre_completo);
        $stmt->bindParam(":cargo_puesto", $this->cargo_puesto);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar participante con ID " . $this->id . ": " . $e->getMessage());
        }

        return false;
    }

    public function delete()
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar participante con ID " . $this->id . ": " . $e->getMessage());
        }

        return false;
    }
}
