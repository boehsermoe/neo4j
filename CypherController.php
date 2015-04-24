<?php

namespace neo4j;

use neo4j\db\Connection;
use Yii;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\FileHelper;

class CypherController extends Controller
{
	public function actionIndex()
	{
		var_dump(Yii::$app->db);
	}
}