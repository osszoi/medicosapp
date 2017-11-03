<?php
  class UserRepository {
    public function __construct() {
      
    }

    private function getTable() {
      return QB::table(User::$tableName);
    }

    public function listAll($pageable = null, $get = null) {
      // Base query
      $baseQuery = $this->getTable()->where('usuario', '!=', 'root');

      if ($get == "patients") {
        $baseQuery->where("es_medico", 0);
      }
      else if ($get == "medics") {
        $baseQuery->where("es_medico", 1);
      }

      $query = $baseQuery->select(array(
        "id",
        "nombre",
        "segundo_nombre",
        "apellido",
        "segundo_apellido",
        "tipo_cedula",
        "cedula",
        "email",
        "usuario",
        "fecha_nacimiento",
        "sexo",
        "estado_civil",
        "estado",
        "direccion",
        "lugar",
        "es_medico",
        QB::raw("concat_ws(' ', nombre, segundo_nombre, apellido, segundo_apellido) as nombre_completo")
      ));

      if ($pageable != null) {
        // Search for keyword if available
        if ($pageable->hasKeyword()) {
          foreach (User::getSearcheableFields() as $sf) {
            $query->orWhere($sf, 'like', '%'.$pageable->getKeyword().'%');
          }
        }
  
        // Add the filters if available
        foreach ($pageable->getFilters() as $filter) {
          $query->where($filter->getField(), $filter->getOperator(), $filter->getValue());
        }
        
        // Add the page
        $query->limit($pageable->getSize())->offset($pageable->getOffset());

        // Set the total elements for the pageable
        $pageable->setTotalElements($baseQuery->count());
      }

      // Run the final query
      $result = Db::run($query);

      // Add the locations and the areas
      $locationRepository = new LocationRepository();
      $areaRelationsRepository = new AreaRelationsRepository();

      foreach ($result as $user) {
        $user->lugar = $locationRepository->find($user->lugar);

        if ($user->es_medico == '1') {
          $user->areas = $areaRelationsRepository->findByUser($user->id);
        }
      }

      return $pageable != null ? $pageable->getResponse($result) : $result;
    }

    public function listAllPatients($pageable = null) {
      return $this->listAll($pageable, "patients");
    }

    public function listAllMedics($pageable = null) {
      return $this->listAll($pageable, "medics");
    }

    public function listNotifications($id) {
      // Base query
      $query = QB::table(Message::$tableName);

      $query->where('user', $id);
      $query->where('leido', 0);
      $query->orderBy('createdAt', 'DESC');

      // Run the final query
      $result = Db::run($query);

      // Add the users
      $userRepository = new UserRepository();
      $areaRepository = new AreaRepository();

      foreach ($result as $m) {
        $m->owner = $userRepository->find($m->owner);
        $m->user = $userRepository->find($m->user);
        $m->area = $areaRepository->find($m->area);
        $m->leido = $m->leido == '0' ? false : true;
      }

      return $result;
    }

    public function find($id) {
      $result = Db::run(
        $this->getTable()
          ->select(array(
            "id",
            "nombre",
            "segundo_nombre",
            "apellido",
            "segundo_apellido",
            "tipo_cedula",
            "cedula",
            "email",
            "usuario",
            "fecha_nacimiento",
            "sexo",
            "estado_civil",
            "estado",
            "direccion",
            "lugar",
            "es_medico",
            QB::raw("concat_ws(' ', nombre, segundo_nombre, apellido, segundo_apellido) as nombre_completo")
          ))
          ->where(User::$pk, '=', $id)
      );

      if (count($result) > 0) {
        $user = $result[0];

        // Add the locations
        $locationRepository = new LocationRepository();
      
        $user->lugar = $locationRepository->find($user->lugar);

        return $user;
      }
      else {
        throw new Exception("There's no record with id " . $id);
      }
    }

    public function add($data) {
      // if (!Session::isActive()) {
      //   throw new MethodNotAllowedException();
      // }

      $this->getTable()->insert($data);

      return $data['id'];
    }

    public function update($data) {
      if (!Session::isActive()) {
        throw new MethodNotAllowedException();
      }

      $this->getTable()->where(User::$pk, $data[User::$pk])->update($data);
    }

    public function patch($data) {
      if (!Session::isActive()) {
        throw new MethodNotAllowedException();
      }

      $this->getTable()->where(User::$pk, $data[User::$pk])->update($data);
    }

    public function delete($data) {
      if (!Session::isActive()) {
        throw new MethodNotAllowedException();
      }

      $this->getTable()->where(User::$pk, $data[User::$pk])->delete();
    }
  }
?>