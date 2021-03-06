<?php
	header('Access-Control-Allow-Origin: https://r02092.github.io');
	$url=parse_url(getenv('DATABASE_URL'));
	$dbh=new PDO('pgsql:dbname='.substr($url['path'],1).';host='.$url['host'],$url['user'],$url['pass']);
	switch($_POST['act']){
		case 'start':
			$id=hexdec($_POST['id']);
			$stmt=$dbh->prepare('SELECT name0,num0,duplicate FROM rooms WHERE id=?');
			$stmt->bindParam(1,$id,PDO::PARAM_INT);
			$stmt->execute();
			$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			if(preg_match('/\d{'.strlen($res['num0']).'}/',$_POST['num'])){
				if(!$res['duplicate']){
					for($i=0;$i<10;$i++){
						if(substr_count($_POST['num'],$i)>1){
							$f=true;
							break;
						}
					}
				}
			}else{
				$f=true;
			}
			if($f){
				echo ($res['duplicate']?1:0).strlen($res['num0']);
				http_response_code(422);
			}else{
				echo strlen($res['duplicate']).$res['name0'];
				$stmt=$dbh->prepare('UPDATE rooms SET num1=:num1,name1=:name1 WHERE id=:id');
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->bindParam(':num1',$_POST['num'],PDO::PARAM_STR);
				$stmt->bindParam(':name1',$_POST['name'],PDO::PARAM_STR);
				$stmt->bindParam(':id',$id,PDO::PARAM_INT);
				$stmt->execute();
			}
			break;
		case 'waitStart':
			$id=hexdec($_POST['id']);
			do{
				sleep(1);
				$stmt=$dbh->prepare('SELECT name1 FROM rooms WHERE id=?');
				$stmt->bindParam(1,$id,PDO::PARAM_INT);
				$stmt->execute();
				$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0]['name1'];
			}while(!isset($res));
			echo $res;
			break;
		case 'genRoom':
			$res=$dbh->query("SELECT id FROM rooms WHERE time>=NOW() - interval '1 week'")->fetchAll(PDO::FETCH_ASSOC);
			do{
				$id=mt_rand(0,255);
			}while(in_array($id,array_column($res,0)));
			$stmt=$dbh->prepare('SELECT EXISTS(SELECT 0 FROM rooms WHERE id=?)');
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->bindParam(1,$id,PDO::PARAM_INT);
			$stmt->execute();
			if($stmt->fetchAll()[0]['exists']){
				$stmt=$dbh->prepare('DELETE FROM rooms WHERE id=?');
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->bindParam(1,$id,PDO::PARAM_INT);
				$stmt->execute();
			}
			$stmt=$dbh->prepare('INSERT INTO rooms (id,time,num0,name0,duplicate) VALUES (:id,NOW(),:num0,:name0,:duplicate)');
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->bindParam(':id',$id,PDO::PARAM_INT);
			$stmt->bindParam(':num0',$_POST['num'],PDO::PARAM_STR);
			$stmt->bindParam(':name0',$_POST['name'],PDO::PARAM_STR);
			$stmt->bindParam(':duplicate',$_POST['duplicate'],PDO::PARAM_INT);
			$stmt->execute();
			echo str_pad(dechex($id),2,0,STR_PAD_LEFT);
			break;
		case 'wait':
			$id=hexdec($_POST['id']);
			do{
				sleep(1);
				if(isset($res))$oldTime=$res['time'];
				$stmt=$dbh->prepare('SELECT ans,time FROM rooms WHERE id=?');
				$stmt->bindParam(1,$id,PDO::PARAM_INT);
				$stmt->execute();
				$res=$stmt->fetchAll(PDO::FETCH_ASSOC)[0];
			}while(!isset($oldTime)||$oldTime==$res['time']);
			echo $res['ans'];
			break;
		case 'judge':
			if(!preg_match('/^[01]$/',$_POST['player']))exit();
			$id=hexdec($_POST['id']);
			$stmt=$dbh->prepare('UPDATE rooms SET ans=:ans,time=NOW() WHERE id=:id');
			$stmt->bindParam(':ans',$_POST['ans'],PDO::PARAM_INT);
			$stmt->bindParam(':id',$id,PDO::PARAM_INT);
			$stmt->execute();
			$stmt=$dbh->prepare('SELECT num'.$_POST['player'].' FROM rooms WHERE id=?');
			$stmt->bindParam(1,$id,PDO::PARAM_INT);
			$stmt->execute();
			$hit=0;
			$blow=0;
			$num = $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['num'.$_POST['player']];
			$ans = $_POST['ans'];
			$len=strlen($ans);
			//$hit????????????????????????????????????$i??????????????????????????????
			//????????????????????????????????????????????????$i????????????
			$movedQuantity = 0;
			for($i=0;$i<$len;$i++){
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
