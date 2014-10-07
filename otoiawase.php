<?php
///////////////////////////////////////////////////////////////////////////////
// PHP+AngularJS+Bootstrapお問い合わせメールフォーム
// Copyright (c) 2014 Walbrix Corporation  http://www.walbrix.com/jp/
// MITライセンス
// 設定ここから ///////////////////////////////////////////////////////////////
define("EMAIL_TO", "お問い合わせ内容を受信するメールアドレス"); // 必須
define("EMAIL_FROM", "メールのFrom欄に記載するアドレス"); // 必須

// ページの <title>要素に入る内容
define("TITLE", "お問い合わせフォーム");

// お問い合わせ内容を通知するメールの題名
define("EMAIL_SUBJECT", "お問い合わせがありました");

// フォーム本体のHTML定義
define("FORM_HTML", <<< 'EOM'
<div class="container">
  <!-- ページタイトル -->
  <h1 class="text-center"><span class="glyphicon glyphicon-envelope"></span> お問い合わせフォーム</h1>

  <!-- フォーム定義開始 -->
  <form name="form" class="form-horizontal" ng-init="sex='無回答'">

    <!-- お名前 -->
    <div class="form-group">
      <label class="col-sm-3 control-label">お名前</label>
      <div class="col-sm-9">
        <input type="text" class="form-control" name="name" ng-model="name" required>
      </div>
    </div>

    <!-- 性別 -->
    <div class="form-group">
      <label class="col-sm-3 control-label">性別</label>
      <div class="col-sm-9">
        <label class="radio-inline"><input type="radio" ng-model="sex" ng-value="'無回答'">無回答/その他</label>
        <label class="radio-inline"><input type="radio" ng-model="sex" ng-value="'男性'">男性</label>
        <label class="radio-inline"><input type="radio" ng-model="sex" ng-value="'女性'">女性</label>
      </div>
    </div>

    <!-- メールアドレス -->
    <div class="form-group">
      <label class="col-sm-3 control-label">メールアドレス</label>
      <div class="col-sm-9">
        <input type="email" class="form-control" name="email" ng-model="email" autocomplete="off" required>
      </div>
    </div>

    <!-- 送信ボタン -->
    <div class="form-group">
      <div class="col-sm-offset-3 col-sm-9">
        <button type="submit" class="btn btn-primary" ng-disabled="!form.$valid" ng-click="submit()"><span class="glyphicon glyphicon-send"></span> この内容で送信する</button>
      </div>
    </div>

  <!-- フォーム定義終了 -->
  </form>

</div>
EOM
);

// フォーム送信後に表示されるモーダルの定義
define("THANKS_HTML", <<< 'EOM'
<div class="modal-header">送信完了</div>
<div class="modal-body">
  お問い合わせありがとうございました。
</div>
<div class="modal-footer">
  <a class="btn btn-primary" href="#" onClick="history.back(); return false;"><span class="glyphicon glyphicon-chevron-left"></span> 戻る</a>
</div>
EOM
);

// 送信前に表示される確認ウィンドウの定義。このdefine自体を削除すれば確認ウィンドウは表示されず即時に送信が行われる。
define("CONFIRM_HTML", <<< 'EOM'
<div class="modal-header">
  <button type="button" class="close" ng-click="$dismiss()">×</button>
  お問い合わせ内容の確認
</div>
<div class="modal-body">
  お名前: {{name}}<br>
  性別: {{sex}}<br>
  メールアドレス: {{email}}<br>
</div>
<div class="modal-footer">
  <a class="btn btn-default" ng-click="$dismiss()">修正する</a>
  <a class="btn btn-primary" ng-click="$close()">送信</a>
</div>
EOM
);
// 設定ここまで ///////////////////////////////////////////////////////////////
mb_language("Japanese");
mb_internal_encoding("UTF-8");
session_start();

$properly_configured = defined("EMAIL_TO") && check_email(EMAIL_TO) && defined("EMAIL_FROM") && check_email(EMAIL_FROM);

function check_email($email) {
  // email address regex from http://blog.livedoor.jp/dankogai/archives/51189905.html
  return preg_match('/^(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:"(?:\\[^\r\n]|[^\\"])*")))\@(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:\[(?:\\\S|[\x21-\x5a\x5e-\x7e])*\])))$/', $email);
}

function check_xsrf_token() {
  $headers = getallheaders();
  return isset($headers["X-XSRF-TOKEN"]) && $headers["X-XSRF-TOKEN"] == session_id();
}

function decode_hex($hex) {
  return mb_convert_encoding(pack('H*',$hex),'UTF-8','UTF-16');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_xsrf_token()) {
    header("HTTP/1.0 403 Forbidden"); 
    echo "403 Forbidden(Token mismatch)";
    exit();
  }

  header('Content-Type: application/json');
  try {
    if (!$properly_configured) {
      throw new Exception("EMAIL_TO 又は EMAIL_FROM定数が正しく設定されていません");
    }
    $json = file_get_contents('php://input');
    if (strlen($json) > 10240) {
      throw new Exception("送信データが大きすぎます (>10kb)");
    }
    $json = json_decode($json, true);
    $json["user_agent"] = $_SERVER['HTTP_USER_AGENT'];
    $json["remote_addr"] = $_SERVER['REMOTE_ADDR'];

    if (defined("JSON_PRETTY_PRINT") && defined("JSON_UNESCAPED_UNICODE")) {
      $json = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    } else {
      $json = print_r($json, TRUE);
    }

    if (!mb_send_mail(EMAIL_TO, EMAIL_SUBJECT, $json, "From: " . EMAIL_FROM)) {
      throw new Exception("システムエラー: メールの送信に失敗しました。");
    }
  }
  catch (Exception $e) {
    $result = Array("success"=>false, "info"=>$e->getMessage());
    echo json_encode($result);
    exit();
  }
  $result = Array("success"=>true, "info"=>null);
  echo json_encode($result);
  session_destroy();
  exit();
}
// else
header('Content-Type: text/html;charset=UTF-8');
if (!function_exists("json_decode")) {
  echo "このPHPは<a href=\"http://php.net/manual/ja/book.json.php\">JSON</a>に対応していません。";
  exit();
}
if (!function_exists("mb_send_mail")) {
  echo "このPHPは<a href=\"http://php.net/manual/ja/mbstring.installation.php\">mbstring</a>に対応していません。";
  exit();
}
$ua = $_SERVER['HTTP_USER_AGENT'];
if ($ua) {
  if (preg_match("/MSIE ([0-9]+)\./", $ua, $matches, PREG_OFFSET_CAPTURE, 0)) {
    $ie_version = (int)$matches[1][0];
    if ($ie_version < 9) {
      echo "古いInternet Explorer(バージョン9未満)は使用できません。検出されたバージョン:" . $ie_version;
      exit();
    }
  }
}
setcookie("XSRF-TOKEN", session_id());
?><html lang="ja" ng-app="Otoiawase">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="http://netdna.bootstrapcdn.com/bootstrap/latest/css/bootstrap.min.css">
    <?php if (defined("STYLESHEET")) {?><link rel="stylesheet" href="<?php echo STYLESHEET ?>"><?php } ?>
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular-resource.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/angular-ui-bootstrap/0.11.0/ui-bootstrap-tpls.min.js"></script>
    <script language="javascript">
      angular.module("Otoiawase", ["ngResource","ui.bootstrap"])
      .run(["$rootScope", "$resource","$modal", function($scope, $resource,$modal) {
        function submit() {
          var obj = {};
          angular.forEach($scope, function(value, key) {
            if (key !== "this" && key !== "form" && key.indexOf("__") !== 0) {
              obj[key] = value;
            }
          }, obj);

<?php if ($properly_configured) { ?>
          var modalInstance = $modal.open({
            templateUrl:"progress.html",
            backdrop:"static",keyboard:false
          });
          $resource("<?php echo basename($_SERVER["SCRIPT_FILENAME"])?>").save({}, obj, function(result) {
            modalInstance.close();
            if (result.success) {
              $modal.open({templateUrl:"thanks.html", backdrop:"static",keyboard:false});
            } else {
              $scope.__message = result.info;
              $modal.open({templateUrl:"error.html",scope:$scope});
            }
          }, function(result) { 
            modalInstance.close();
            $scope.__message = "HTTPエラー: " + result.data;
            $modal.open({templateUrl:"error.html",scope:$scope});
          });
<?php } else { ?>
          $scope.__values = obj;
          $modal.open({templateUrl:"show-email.html",scope: $scope });
<?php } ?>
        }
        $scope.submit = function() {
<?php if (defined("CONFIRM_HTML")) { ?>
          $modal.open({templateUrl:"confirm.html",scope:$scope}).result.then(function() {
            submit();
          });
<?php } else { ?>
	  submit();
<?php } ?>
	}
      }])
    </script>
    <title><?php echo TITLE;?></title>
  </head>
  <body>
    <?php echo FORM_HTML ?>
    <script type="text/ng-template" id="progress.html">
      <div class="modal-header">送信中...</div>
      <div class="modal-body">
        <progressbar class="progress-striped active" animate="false" value="100">
        </progressbar>
      </div>
    </script>
    <script type="text/ng-template" id="thanks.html">
      <?php echo THANKS_HTML ?>
    </script>
    <script type="text/ng-template" id="error.html">
      <div class="modal-header">
        <button type="button" class="close" ng-click="$dismiss()">×</button>
        エラー
      </div>
      <div class="modal-body">
        {{__message}}
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger" ng-click="$close()">閉じる</button>
      </div>
    </script>
<?php if (defined("CONFIRM_HTML")) { ?>
    <script type="text/ng-template" id="confirm.html">
      <?php echo CONFIRM_HTML ?>
    </script>
<?php } ?>
<?php if (!$properly_configured) { ?>
    <script type="text/ng-template" id="show-email.html">
      <div class="modal-header">
        <button type="button" class="close" ng-click="$dismiss()">×</button>
        <?php echo EMAIL_SUBJECT ?>
      </div>
      <div class="modal-body">
        <p>{{ __values | json}}</p>
	<div class="alert alert-danger">送信先メールアドレス(EMAIL_TO) 又は 送信元メールアドレス(EMAIL_FROM)が正しく設定されていないため、メールが送信される代わりにこのウィンドウが表示されています。</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" ng-click="$close()">閉じる</button>
      </div>
    </script>
<?php } ?>
  </body>
  <!-- Copyright (c) 2014 Walbrix Corporation  http://www.walbrix.com/jp/ -->
</html>

