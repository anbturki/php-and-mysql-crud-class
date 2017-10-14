the class provides basics functionality to deal with a MySQL databases using **PDO** .


```PHP
$db = new Databaes();
$db->table("users")->select(['id','name']);
```