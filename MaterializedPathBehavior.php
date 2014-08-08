<?php
/**
 * MaterializedPathBehavior
 *
 * This behavior adds materialized path tree methods to a ActiveRecord model.
 *
 * It can be  be attached to a model on its behaviors() method:
 * <pre>
 * public function behaviors()
 * {
 *     return array(
 *         'mp' => array(
 *             'class'=>'ext.behaviors.materialized-path-behavior.MaterializedPathBehavior',
 *         ),
 *     );
 * }
 * </pre>
 *
 * @author Andrey Golovin <matperez@mail.ru>
 * @version 0.0.1
 *
 * @property CActiveRecord|MaterializedPathTree $owner
 */
class MaterializedPathBehavior extends CActiveRecordBehavior {
	/**
	 * @var int - максимальный уровень вложенности
	 */
	public $maxLevel = 32;

	/**
	 * @var string - название поля содержащего путь элемента
	 */
	public $pathField = 'path';

	/**
	 * @var string - разделитель элементов пути
	 */
	public $pathSeparator = '.';

	/**
	 * @var string - название поля, содержащего позицию элемента
	 */
	public $positionFiled = 'position';

	/**
	 * @var string - название поля, содержащего уровень элемента
	 */
	public $levelField = 'level';

	/**
	 * @var array
	 */
	private $_children = array();

	/**
	 * @var bool
	 */
	private $_treeIsLoaded = false;

	/**
	 * @var mixed - CDbCriteria with condition
	 */
	public $with = null;

	/**
	 * @return bool - является ли элемент корневым
	 */
	public function isRoot() {
		return !$this->owner->getParentId();
	}

	/**
	 * @return bool
	 */
	public function getIsRoot() {
		return $this->isRoot();
	}

	/**
	 * @return bool
	 */
	public function isLeaf() {
		return !$this->owner->getHasChildren();
	}

	/**
	 * @param CActiveRecord $model
	 * @return bool
	 */
	public function isDescendant(CActiveRecord $model) {
		return $this->owner->isParent($model, true);
	}

	/**
	 * @return bool
	 */
	public function getHasChildren() {
		return !!count($this->owner->getChildren());
	}

	/**
	 * @param int|null $position
	 * @return CActiveRecord
	 */
	public function setPosition($position = null) {
		$path = ($this->owner->getParentId()) ? $this->getParent()->{$this->pathField} : '.' ;
		$posFrom = (int) $this->owner->{$this->positionFiled};
		/** @var CActiveRecord $model */
		$model = $this->owner;
		if ($position) {
			$posTo = (int) $position;
			$lower = $posTo < $posFrom;
			$criteria = new CDbCriteria();
			$criteria
				->addSearchCondition('path', $path.'%', false)
				->compare('level', $model->{$this->levelField})
				->addBetweenCondition('position', min($posFrom, $posTo), max($posFrom, $posTo));
			$model->dbConnection->createCommand()
				->update($model->tableName(), array(
					'position' => new CDbExpression('position' . ($lower ? '+' : '-') . 1)
				), $criteria->condition, $criteria->params);
			$model->{$this->positionFiled} = $position;
			// @todo выяснить почему не проходит валидация, при использовании MultilingualBehavior
			$model->save(false);
		} else {
			$criteria = new CDbCriteria();
			$criteria
				->addSearchCondition('path', $path.'%', false)
				->compare('level', $model->{$this->levelField})
				->compare('position >', $posFrom);
			$model->dbConnection->createCommand()
				->update($model->tableName(), array(
					'position' => new CDbExpression('position - 1')
				), $criteria->condition, $criteria->params);
		}
		return $model;
	}

	/**
	 * Mode node position up
	 */
	public function moveUp() {
		$this->owner->setPosition($this->owner->{$this->positionFiled} - 1);
	}

	/**
	 * Move node position down
	 */
	public function moveDown() {
		$this->owner->setPosition($this->owner->{$this->positionFiled} + 1);
	}

	/**
	 * @return CActiveRecord|null
	 */
	public function getParent() {
		return ($this->owner->getParentId()) ? $this->owner->model()->findByPk($this->owner->getParentId()) : null;
	}

	/**
	 * @return CActiveRecord[]
	 */
	public function getParents() {
		$ids = $this->owner->getParentIds();
		$criteria = new CDbCriteria();
		$criteria->addInCondition('t.id', $ids);
		$parents = $this->owner->findAll($criteria);
		return $parents;
	}

	/**
	 * @param array|CDbCriteria $addCriteria
	 * @return array|CActiveRecord[]
	 */
	public function getRoots($addCriteria = array()) {
		/** @var CActiveRecord $model */
		$model = $this->owner;
		$criteria = new CDbCriteria();
		$criteria->compare('path','.');
		$criteria->mergeWith($addCriteria);
		return $model->model()->findAll($criteria);
	}

	/**
	 * @param mixed $model
	 * @return CActiveRecord|mixed
	 */
	public function target($model) {
		if (is_int($model))
			return $this->owner->model()->findByPk($model);
		else
			return $model;
	}

	/**
	 * Move current model to $target's children
	 * @param CActiveRecord|int $target
	 * @param bool $new
	 * @return CActiveRecord
	 */
	public function move($target = null, $new = false) {
		$target = $this->target($target);
		/** @var CActiveRecord|MaterializedPathTree $model */
		$model = $this->owner;
		// preventing moving node to them self
		if ($target && $target->primaryKey == $model->primaryKey) {
			return $model;
		}
		$model->setPosition(null);
		$children = $model->getChildren();
		if ($target && $target->primaryKey) {
			if ($target->{$this->levelField} == $this->maxLevel) {
				/** @var CActiveRecord|MaterializedPathTree $target */
				$target = $target->getParent();
			}
			$model->{$this->levelField} = $target->{$this->levelField} + 1;
			$model->{$this->pathField}  = $target->{$this->pathField} . $target->primaryKey . '.';
			$this->{$this->positionFiled} = count($target->getChildren()) + 1;
			$target->addChild($model);
		} else {
			$model->{$this->levelField} = 0;
			$model->{$this->pathField} = '.';
			$rootsCount = $model->countByAttributes(array(
				$this->pathField => '.'
			));
			$model->{$this->positionFiled} = $rootsCount ? $rootsCount + ($new ? 0 : 1) : 0;
		}
		// @todo выяснить почему не проходит валидация, при использовании MultilingualBehavior
		$model->save(false);
		$this->_children = array();
		foreach ($children as $child) {
			/** @var CActiveRecord|MaterializedPathTree $child */
			$child->move($model);
		}
		return $model;
	}

	/**
	 * @param CDbCriteria|array $addCriteria
	 * @param bool $forceReload
	 * @return CActiveRecord|MaterializedPathTree
	 */
	public function loadTree($addCriteria = array(), $forceReload = false) {
		if($this->_treeIsLoaded && !$forceReload)
			return $this;
		$this->_treeIsLoaded = true;
		$criteria = new CDbCriteria();
		if ($criteria->with)
			$criteria->with = $this->with;
		if ($this->owner->{$this->pathField} || $this->owner->primaryKey) {
			$path = $this->owner->primaryKey ? "%.{$this->owner->primaryKey}.%" : $this->owner->{$this->pathField} . '%';
			$criteria->addSearchCondition($this->pathField, $path, false);
		}
		$criteria->order = $this->positionFiled;
		$criteria->mergeWith($addCriteria);
		$items = $this->owner->model()->findAll($criteria);
		$levels = array();
		foreach($items as $item) {
			$l = $item->{$this->levelField};
			if (empty($levels[$l]))
				$levels[$l] = array();
			$levels[$l][] = $item;
		}
		ksort($levels);
		foreach($levels as $level) {
			foreach($level as $element) {
				$this->addDescendant($element);
			}
		}
		return $this->owner;
	}

	/**
	 * @return array
	 */
	public function getChildren() {
		if(!$this->_treeIsLoaded)
			return $this->owner->loadTree()->getChildren();
		return $this->_children;
	}

	/**
	 * @param CActiveRecord $model
	 * @return $this
	 */
	public function addDescendant(CActiveRecord $model) {
		if ($this->isParent($model)) {
			$this->addChild($model);
		} else {
			/** @var CActiveRecord|MaterializedPathTree $child */
			$child = $this->getChildParentOf($model);
			$child && $child->addDescendant($model);
		}
	}

	/**
	 * @param CActiveRecord|MaterializedPathTree $model
	 * @return CActiveRecord|MaterializedPathTree
	 */
	public function getChildParentOf(CActiveRecord $model) {
		foreach ($this->_children as $child) {
			if (in_array($child->primaryKey, $model->getParentIds())) return $child;
		}
		return null;
	}

	/**
	 * @param CActiveRecord $model
	 * @return $this
	 */
	public function addChild(CActiveRecord $model) {
		$this->_children[$model->primaryKey] = $model;
		return $this->owner;
	}

	/**
	 * @param CActiveRecord $model
	 * @return $this
	 */
	public function removeChild(CActiveRecord $model) {
		unset($this->_children[$model->primaryKey]);
		return $this->owner;
	}

	/**
	 * @return array - массив идентификаторов родительских элементов
	 */
	public function getParentIds() {
		$ids = explode($this->pathSeparator, trim($this->owner->{$this->pathField}, $this->pathSeparator));
		//array_pop($ids);
		foreach ($ids as &$v) {
			$v = (int) $v;
		}
		return $ids;
	}

	/**
	 * @return mixed - идентификатор непосредственного родителя
	 */
	public function getParentId() {
		$ids = $this->getParentIds();
		return array_pop($ids);
	}

	/**
	 * @param CActiveRecord|MaterializedPathTree $model
	 * @param bool $fullPath
	 * @return bool
	 */
	public function isParent(CActiveRecord $model, $fullPath = false) {
		return $fullPath ?
			in_array($this->owner->primaryKey, $model->getParentIds()) :
			$this->owner->primaryKey == $model->getParentId();
	}

	/**
	 * @param CActiveRecord|MaterializedPathTree $model
	 * @return bool
	 */
	public function isChild(CActiveRecord $model) {
		return $model->isParent($this->owner);
	}

	/**
	 * @param CActiveRecord|MaterializedPathTree $model
	 * @return bool
	 */
	public function isSibling(CActiveRecord $model) {
		return $this->owner->getParentId() == $model->getParentId();
	}

	/**
	 * @param CEvent $event
	 */
	public function beforeDelete($event) {
		/** @var CActiveRecord $owner */
		$owner = $this->owner;
		if ($owner->hasChildren) {
			foreach($owner->children as $child) {
				$child->delete();
			}
		}
	}

}
