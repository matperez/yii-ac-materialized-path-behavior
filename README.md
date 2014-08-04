yii-ac-materialized-path-behavior
=================================

Древовидная структура "материализованный путь" для YII.

Использоваение:

1. Скопировать в папку ./protected/extensions/behaviors/

2. Добавить поведение в модель

 * <pre>
 * public function behaviors()
 * {
 *     return array(
 *         'MaterializedPathTree' => array(
 *             'class'=>'ext.behaviors.MaterializedPathTree',
 *         ),
 *     );
 * }
 * </pre>

Модель должна содержать подходящие поля для хранения параметров path, level, position
