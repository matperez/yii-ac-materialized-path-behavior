yii-ac-materialized-path-behavior
=================================

Древовидная структура "материализованный путь" для YII.

Использоваение:

1. Скопировать в папку ./protected/extensions/behaviors/

2. Добавить поведение в модель

```php
 public function behaviors()
 {
    return array(
        'MaterializedPathTree' => array(
            'class'=>'ext.behaviors.MaterializedPathTree',
            'pathField' => 'path',
            'positionField' => 'position',
            'levelField' => 'level',
        ),
    );
 }
``` 

Модель должна содержать подходящие поля для хранения параметров path, level, position.

Использование
===

```php
// Сделать корневым элементом
$model->move(null);

// Сделать дочерним элементов объекта с id=1
$target = Item::model()->findByPk(1);
$model->move($target);

// Получить все корневые узлы
Item::model()->getRoots();

// Получить всех потомков заданного узла
$model->children;
foreach($model->children as $child) {
 var_dump($child->prop);
}

// Получить идентификаторы родителей
$model->getParentIds();

// Получить идентификатор непосредственного родителя
$model->getParentId();

// Изменение позиции узла
$model->setPosition(2);
$model->moveUp();
```
