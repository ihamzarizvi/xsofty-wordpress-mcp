<?php
require __DIR__ . '/bootstrap.php';
function expect_download($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['xwmcp_options']=[]; $GLOBALS['xwmcp_transients']=[]; $GLOBALS['xwmcp_current_user']=1;
$created=Xsofty_MCP_Auth::create_token('Backup operator',1,['backups'],time()+3600,['get_backup_download_url']);
expect_download(Xsofty_MCP_Auth::authorize(new WP_REST_Request(['Authorization'=>'Bearer '.$created['token']]))===true,'Token must authorize.');
$directory=Xsofty_MCP_Backups::directory(); wp_mkdir_p($directory); $filename='xsofty-backup-test-token-link.zip'; file_put_contents($directory.'/'.$filename,'test archive'); chmod($directory.'/'.$filename,0600);
$url=Xsofty_MCP_Backups::signed_download_url($filename,300); parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
expect_download(($query['token']??'')===$created['id'],'Signed URL must identify the issuing token without exposing its secret.');
expect_download(Xsofty_MCP_Backups::verify_download($filename,(int)$query['expires'],(string)$query['signature'],(string)$query['token']),'Issuing token key must verify its URL.');
Xsofty_MCP_Auth::revoke_token($created['id']);
expect_download(!Xsofty_MCP_Backups::verify_download($filename,(int)$query['expires'],(string)$query['signature'],(string)$query['token']),'Revoking one token must invalidate its outstanding signed URLs.');
unlink($directory.'/'.$filename);
print("TOKEN_DOWNLOAD_TESTS_PASS\n");
