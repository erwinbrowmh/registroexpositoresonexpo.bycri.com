<?php
class Participante {
    private $conn;
    private $table_name = "participantes";

    public $id;
    public $expositor_id;
    public $nombre_completo;
    public $cargo_puesto;
    public $created_at;
    public $updated_at;

    public $error; // Para almacenar mensajes de error de PDO
    public $errorCode; // Para almacenar códigos de error de PDO

    public function __construct($db) {
        $this->conn = $db;
    }

    function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      expositor_id=:expositor_id,
                      nombre_completo=:nombre_completo,
                      cargo_puesto=:cargo_puesto";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->expositor_id = htmlspecialchars(strip_tags($this->expositor_id));
        $this->nombre_completo = htmlspecialchars(strip_tags($this->nombre_completo));
        $this->cargo_puesto = htmlspecialchars(strip_tags($this->cargo_puesto));

        // bind values
        $stmt->bindParam(":expositor_id", $this->expositor_id);
        $stmt->bindParam(":nombre_completo", $this->nombre_completo);
        $stmt->bindParam(":cargo_puesto", $this->cargo_puesto);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        $this->error = $stmt->errorInfo()[2];
        $this->errorCode = $stmt->errorCode();
        return false;
    }

    function read() {
        $query = "SELECT
                    id, expositor_id, nombre_completo, cargo_puesto, created_at, updated_at
                  FROM
                    " . $this->table_name . "
                  ORDER BY
                    created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    function readOne() {
        $query = "SELECT
                    id, expositor_id, nombre_completo, cargo_puesto, created_at, updated_at
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
            $this->expositor_id = $row['expositor_id'];
            $this->nombre_completo = $row['nombre_completo'];
            $this->cargo_puesto = $row['cargo_puesto'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      expositor_id=:expositor_id,
                      nombre_completo=:nombre_completo,
                      cargo_puesto=:cargo_puesto,
                      updated_at=CURRENT_TIMESTAMP
                  WHERE
                      id = :id";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->expositor_id = htmlspecialchars(strip_tags($this->expositor_id));
        $this->nombre_completo = htmlspecialchars(strip_tags($this->nombre_completo));
        $this->cargo_puesto = htmlspecialchars(strip_tags($this->cargo_puesto));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // bind values
        $stmt->bindParam(":expositor_id", $this->expositor_id);
        $stmt->bindParam(":nombre_completo", $this->nombre_completo);
        $stmt->bindParam(":cargo_puesto", $this->cargo_puesto);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        $this->error = $stmt->errorInfo()[2];
        $this->errorCode = $stmt->errorCode();
        return false;
    }

    function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }

        $this->error = $stmt->errorInfo()[2];
        $this->errorCode = $stmt->errorCode();
        return false;
    }
}
?>