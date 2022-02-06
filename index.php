<?php
	ini_set('display_errors',1);
	header('Access-Control-Allow-Origin: https://r02092.github.io');
	$url=parse_url(getenv('DATABASE_URL'));
	$dbh=new PDO('pgsql:dbname='.substr($url['path'],1).';host='.$url['host'],$url['user'],$url['pass']);
	switch($_POST['act']){
		case 'start':
			$stmt=$dbh->prepare('UPDATE rooms SET num1="?",name1="?" WHERE id=?');
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->bindParam(1,$_POST['num'],PDO::PARAM_STR);
			$stmt->bindParam(2,$_POST['name'],PDO::PARAM_STR);
			$stmt->bindParam(3,$_POST['id'],PDO::PARAM_INT);
			$stmt->execute();
			$stmt=$dbh->prepare('SELECT name0,duplicate FROM rooms WHERE id=?');
			$stmt->bindParam(1,hexdec($_POST['id']),PDO::PARAM_INT);
			$stmt->execute();
			$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			echo $res['duplicate'].$res['name0'];
			break;
		case 'waitStart':
			do{
				sleep(1);
				$stmt=$dbh->prepare('SELECT name1 FROM rooms WHERE id=?');
				$stmt->bindParam(1,hexdec($_POST['id']),PDO::PARAM_INT);
				$stmt->execute();
				$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0]['name1'];
			}while(!is_null($res));
			echo $res;
			break;
		case 'genRoom':
			$res=$dbh->query('SELECT id FROM rooms WHERE time>=now() - interval "1 week"')->fetchAll(PDO::FETCH_ASSOC);
			do{
				$id=mt_rand(0,255);
			}while(!in_array($id,array_column($res,0)));
			$stmt=$dbh->prepare('SELECT EXISTS(SELECT 0 FROM rooms WHERE id=?)');
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->bindParam(1,$id,PDO::PARAM_INT);
			$stmt->execute();
			if($stmt->fetchAll(PDO::FETCH_ASSOC||PDO::FETCH_COLUMN)[0]){
				$stmt=$dbh->prepare('DELETE FROM rooms WHERE id=?');
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->bindParam(1,$id,PDO::PARAM_INT);
				$stmt->execute();
			}
			$stmt=$dbh->prepare('INSERT INTO rooms (id,num0,name0,duplicate) VALUES (?,"?","?",?)');
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->bindParam(1,$id,PDO::PARAM_INT);
			$stmt->bindParam(2,$_POST['num'],PDO::PARAM_STR);
			$stmt->bindParam(3,$_POST['name'],PDO::PARAM_STR);
			$stmt->bindParam(4,$_POST['duplicate'],PDO::PARAM_INT);
			$stmt->execute();
			echo dechex($id);
			break;
		case 'wait':
			$stmt=$dbh->prepare('SELECT time FROM rooms WHERE id=?');
			$stmt->bindParam(1,hexdec($_POST['id']),PDO::PARAM_INT);
			$stmt->execute();
			$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			do{
				sleep(1);
				$oldTime=$res['time'];
				$stmt=$dbh->prepare('SELECT ans,time FROM rooms WHERE id=?');
				$stmt->bindParam(1,hexdec($_POST['id']),PDO::PARAM_INT);
				$stmt->execute();
				$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			}while($oldTime==$res['time']);
			echo $res['ans'];
			break;
		case 'judge':
			$stmt=$dbh->prepare('UPDATE rooms SET ans="?" WHERE id=?');
			$stmt->bindParam(1,$_POST['ans'],PDO::PARAM_INT);
			$stmt->bindParam(2,hexdec($_POST['id']),PDO::PARAM_INT);
			$stmt->execute();
			$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			$stmt=$dbh->prepare('SELECT num? FROM rooms WHERE id=?');
			$stmt->bindParam(1,$_POST['playerId'],PDO::PARAM_INT);
			$stmt->bindParam(2,hexdec($_POST['id']),PDO::PARAM_INT);
			$stmt->execute();
			$hit=0;
			$blow=0;
			$num = $stmt->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_COLUMN)[0];
			$ans = $_POST['ans'];
			//$hitのときに数字を削除すると$iの番号とズレが生じる
			//そのため数字を削除した数の分だけ$iから引く
			$movedQuantity = 0;
			for($i=0;$i<strlen($ans);$i++){
				if($num[$i-$movedQuantity]==$ans[$i-$movedQuantity]){
					$hit++;
					$num = removeCharacter($i-$movedQuantity,$num);
					$ans = removeCharacter($i-$movedQuantity,$ans);
					$movedQuantity++;
				}else if(strpos($num,$ans[$i-$movedQuantity])!==false){
					$blow++;
				}
			}
			if($hit==strlen($ans)){
				$stmt=$dbh->prepare('DELETE FROM rooms WHERE id=?');
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->bindParam(1,$id,PDO::PARAM_INT);
				$stmt->execute();
			}
			echo $hit.' '.$blow;
	}
	function removeCharacter($position,$str){
		return substr_replace($str,'',$position,1);
	}
?>
