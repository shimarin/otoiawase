<?php
// 設定ここから ///////////////////////////////////////////////////////////////
define("EMAIL_TO", "お問い合わせ内容を受信するメールアドレス"); // 必須
define("EMAIL_FROM", "メールのFrom欄に記載するアドレス"); // 必須

define("BACK_TO", null); // フォーム送信成功後の戻り先URL nullの場合戻るボタン非表示
define("BACK_TO_TEXT", "戻る");

define("FORM_HTML", "form.html");
define("TITLE", "お問い合わせフォーム");
define("THANKS_MESSAGE", "お問い合わせありがとうございました。");

define("EMAIL_SUBJECT", "お問い合わせがありました");
// 設定ここまで ///////////////////////////////////////////////////////////////
mb_language("Japanese");
mb_internal_encoding("UTF-8");
session_start();

function check_email($email) {
  // email address regex from http://blog.livedoor.jp/dankogai/archives/51189905.html
  return preg_match('/^(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:"(?:\\[^\r\n]|[^\\"])*")))\@(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:\[(?:\\\S|[\x21-\x5a\x5e-\x7e])*\])))$/', $email);
}

function check_xsrf_token() {
  $headers = getallheaders();
  return isset($headers["X-XSRF-TOKEN"]) && $headers["X-XSRF-TOKEN"] == session_id();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_xsrf_token()) {
    header("HTTP/1.0 403 Forbidden"); 
    echo "403 Forbidden(Token mismatch)";
    exit();
  }

  header('Content-Type: application/json');
  try {
    $json = file_get_contents('php://input');
    if (strlen($json) > 10240) {
      throw new Exception("送信データが大きすぎます (>10kb)");
    }
    $json = json_decode($json, true);
    $json["user_agent"] = $_SERVER['HTTP_USER_AGENT'];
    $json["remote_addr"] = $_SERVER['REMOTE_ADDR'];
    $json = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
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
setcookie("XSRF-TOKEN", session_id());
?><html lang="ja" ng-app="Otoiawase">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="http://netdna.bootstrapcdn.com/bootstrap/latest/css/bootstrap.min.css">
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.20/angular.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.20/angular-resource.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/angular-ui-bootstrap/0.11.0/ui-bootstrap-tpls.min.js"></script>
    <script language="javascript">
      angular.module("Otoiawase", ["ngResource","ui.bootstrap"])
      .run(["$rootScope", "$resource","$modal", function($scope, $resource,$modal) {
        $scope.submit = function() {
          var obj = {};
          angular.forEach($scope, function(value, key) {
            if (key !== "this" && key !== "form") {
              obj[key] = value;
            }
          }, obj);
          var modalInstance = $modal.open({
            templateUrl:"progress.html",
            backdrop:"static",keyboard:false
          });
          $resource("<?php echo basename($_SERVER["SCRIPT_FILENAME"])?>").save({}, obj, function(result) {
            modalInstance.close();
            if (result.success) {
              $modal.open({templateUrl:"thanks.html", backdrop:"static",keyboard:false});
            } else {
              $scope.message = result.info;
              $modal.open({templateUrl:"error.html",scope:$scope});
            }
          }, function(result) { 
            modalInstance.close();
            $scope.message = "HTTPエラー: " + result.data;
            $modal.open({templateUrl:"error.html",scope:$scope});
          });
        }
      }])
    </script>
    <title><?php echo TITLE;?></title>
  </head>
  <body>
    <?php if (!check_email(EMAIL_TO)) {?>
      <span class="text-danger">警告: 送信先メールアドレス(EMAIL_TO)が正しく設定されていません。</span><br>
    <?php }?>
    <?php if (!check_email(EMAIL_FROM)) {?>
      <span class="text-danger">警告: 送信元メールアドレス(EMAIL_FROM)が正しく設定されていません。</span><br>
    <?php }?>
    <?php if (!function_exists("json_decode")) {?>
      <span class="text-danger">警告: このPHPはJSONに対応していません。</span><br>
    <?php }?>
    <?php if (!function_exists("mb_send_mail")) {?>
      <span class="text-danger">警告: このPHPはmbstringに対応していません。</span><br>
    <?php }?>
    <?php if (!@include(FORM_HTML)) {?>
      <span class="text-danger">エラー: <?php echo FORM_HTML?>が設置されていません。</span><br>
    <?php }?>
    <script type="text/ng-template" id="progress.html">
      <div class="modal-header">送信中...</div>
      <div class="modal-body">
        <progressbar class="progress-striped active" animate="false" value="100">
        </progressbar>
      </div>
    </script>
    <script type="text/ng-template" id="thanks.html">
      <div class="modal-header">送信完了</div>
      <div class="modal-body">
         <?php echo THANKS_MESSAGE ?>
      </div>
      <?php if (BACK_TO) {?>
      <div class="modal-footer">
        <a class="btn btn-primary" href="<?php echo BACK_TO?>"><?php echo BACK_TO_TEXT?></a>
      </div>
      <?php }?>
    </script>
    <script type="text/ng-template" id="error.html">
      <div class="modal-header">エラー</div>
      <div class="modal-body">
        {{message}}
      </div>
      <?php if (BACK_TO) {?>
      <div class="modal-footer">
        <button class="btn btn-danger" ng-click="$close()">OK</button>
      </div>
      <?php }?>
    </script>
  </body>
</html>
