<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$email=trim(readline('Admin email: '));$password=readline('Strong admin password (min 12 chars): ');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Invalid email or password too short.\n");exit(1);}
$hash=password_hash($password,PASSWORD_DEFAULT);$s=db()->prepare("INSERT INTO admins(email,password_hash) VALUES(?,?)");$s->execute([$email,$hash]);echo "Admin created. Delete this script from the server now.\n";
