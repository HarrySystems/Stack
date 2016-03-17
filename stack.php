<?php
	ob_start();

	$_msg = array();
	$_RECORD = array();
	$_DEBUG = true;

	// Inicia exibição de todos os erros
		error_reporting(E_ALL);
		ini_set("display_errors", true);
		ini_set("html_errors", false);

	// ERRORS/WARNINGS
		$_WARNINGS = array();
		$_ERRORS = array();
	
		function errorHandler(
			$errno, 
			$errstr, 
			$errfile, 
			$errline
		)
		{
			Global $_WARNINGS;
			Global $_ERRORS;

			if ( !error_reporting () ) {
		        // Error reporting is currently turned off or suppressed with @
		        return;
		    }

			$arrErrorInfo = array	(
				"id" => $errno, 
				"message" => $errstr, 
				"file" => $errfile, 
				"line" => $errline
			);
			
			switch($errno){
				case E_WARNING:
				case E_USER_WARNING:
				case E_NOTICE:
				case E_USER_ERROR:
					$_WARNINGS[] = $arrErrorInfo;
				break;
				
				default:
					$_ERRORS[] = $arrErrorInfo;
				break;
			}
			
			return true;
		}
		
		set_error_handler("errorHandler");
	
	// FATAL ERRROS
		function shutdownHandler(){
			Global $_ERRORS;
			$lasterror = error_get_last();
			
			$_ERRORS[] = array(
				"id" => $lasterror['type'], 
				"message" => $lasterror['message'], 
				"file" => $lasterror['file'], 
				"line" => $lasterror['line']
			);
			
			// Solução provisória
				if(!empty($lasterror)){
					ob_end_clean();

					header("Content-Type: text/html");
					echo "FATAL ERROR: ".$lasterror['message']."  <br>LINE: ".$lasterror['line']."<br>FILE:".$lasterror['file'];
				}
				else
				{
				}
					sendToConsole();
		}

		register_shutdown_function("shutdownHandler");

	// EXCEPTIONS
		function exceptionHandler($exception) {
			ob_end_clean();
			echo "EXCEPTION: ".$exception->getMessage()." <br>LINE: ".$exception->getLine()."<br> FILE: ".$exception->getFile();
		}

		set_exception_handler('exceptionHandler');

	// MESSAGES
		function stack(
			$message, 
			$name = ''
		)
		{
			if(!empty($name))
				$GLOBALS['_msg'][$name] = $message;
			else
				$GLOBALS['_msg'][] = $message;
		}

		function fixMessage($message)
		{
			if(!is_array($message))
			{
				$message = str_replace("'", "`", $message);
				$message = str_replace("\\", "/", $message);
				$message = str_replace("\n", "\\n", $message);
				$message = str_replace("\r", "\\r", $message);
			}

			return $message;

		}


	// SEND INFO TO CONSOLE
		function sendToConsole()
		{
			Global $_DEBUG;
			Global $_WARNINGS;
			Global $_ERRORS;
			Global $_msg;
			Global $_RECORD;

			if 	(
						empty($_SERVER['HTTP_X_REQUESTED_WITH'])
					||	(
								!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
							&&	strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
						)
				) 
			{
				$_DEBUG = true;
			}
			else
			{
				$_DEBUG = false;
			}

			if($_DEBUG)
			{
				header("Content-Type: text/html");

				// CONSOLE
					echo "
						<script id='_stack'>
							if (window.console)
							{
					";

					// BASE
						$_PAGENAME = substr($_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'],"/") + 1, strlen($_SERVER['PHP_SELF']));
						$_MEMUSAGE = @round($size/pow(1024*8,($i=floor(log(memory_get_usage(true),1024*8)))),2).' '.array('B','KB','MB','GB','TB','PB')[$i];
						$_REQTIME = round((microtime(true) - $_SERVER['REQUEST_TIME']), 2);
					
						// Inicia a session, caso ainda não tenha sido iniciada na página
							@session_start();

						// Desconsidera repetição de erros e warnings
							$_WARNINGS = array_unique($_WARNINGS, SORT_REGULAR);						
							$_ERRORS = array_unique($_ERRORS, SORT_REGULAR);

					// CONSOLE
						// SERVER
							$table_server = "";
							foreach ($_SERVER as $key => $value)
								$table_server .= "'".$key."':{'value': '".str_replace("\\","\\\\", str_replace("\n", "\\n", str_replace("\r","", $value)))."'},";
							
							echo 	"
										if(window.self === window.top)
										{
											var table_server = {".rtrim($table_server, ",")."};
											console.groupCollapsed('SERVER')
											console.table(table_server);
											console.groupEnd();

											/*var storageHandler = function () {
											    alert('storage event 1');
											};

											window.addEventListener('storage', storageHandler, false);*/
										}
									";
						// PAGE
							echo 	"console.group('".basename($_SERVER['PHP_SELF'])."');";

							// INFO
								// MEMORY USAGE
									echo "console.info('Memory usage: ".$_MEMUSAGE."');";

								// REQUEST START
									echo 	"console.info('Request start: ".
											date(
												"Y-m-d H:i:s",
												$_SERVER['REQUEST_TIME']
											).
											"');";

								// REQUEST FINISH
									echo 	"console.info('Request finish: ".
											date(
												"Y-m-d H:i:s",
												$_SERVER['REQUEST_TIME'] + $_REQTIME
											).
											"');";

								// REQUEST TIME
									echo "console.info('Request time: ".$_REQTIME."');";
								
								// REQUEST
									if 	(
												count($_GET) > 0
											||	count($_POST) > 0
											||	count($_SESSION) > 0
										)
									{
										// GET
											if(count($_GET) > 0)
											{
												echo "
													console.groupCollapsed('GET');
													console.dir(".json_encode($_GET)." );
													console.groupEnd();
												";
											}

										// POST
											if(count($_POST) > 0)
											{
												echo "
													console.groupCollapsed('POST');
													console.dir(".json_encode($_POST)." );
													console.groupEnd();
												";
											}
											

										// SESSION
											if(count($_SESSION) > 0)
											{
												echo "
													console.groupCollapsed('SESSION');
													console.dir(".json_encode($_SESSION)." );
													console.groupEnd();
												";
											}
									}

							// MESSAGES
								if (count($_msg) > 0)
								{
									echo "console.groupCollapsed('MESSAGES');";
									foreach ($_msg as $key => $value) 
										echo "
											console.groupCollapsed('".$key."')
											console.".
												(
														is_array($value) 
													// ? 	"dir" 
													?	"dir"
													: 	"log" 
												)
												."(\"".
												str_replace(
													"\n", 
													"\\n", 
													str_replace(
														"\r",
														"", 
														$value
													)
												)
												."\");
											console.groupEnd();
										";
									echo "console.groupEnd();";
								}

							// RECORDS
								if (count($_RECORD) > 0)
								{
									echo "console.groupCollapsed('RECORDS');";
									foreach ($_RECORD as $key => $value) 
										echo "
											console.groupCollapsed('".$key."')
											console.log".
												"(\"".
												str_replace(
													"\n", 
													"\\n", 
													str_replace(
														"\r",
														"", 
														$value
													)
												)
												."\");
											console.groupEnd();
										";
									echo "console.groupEnd();";
								}

							// WARNINGS	
								foreach ($_WARNINGS as $key => $value)
									echo "console.warn('Line ".$value['line'].": ".fixMessage($value['message'])."');";
							
							// ERRORS
								foreach ($_ERRORS as $key => $value)
									if(!empty($value['message']))
										echo "console.error('Line ".$value['line'].": ".fixMessage($value['message'])."');";
								
							echo 	"
									console.groupEnd();
							";
					echo "
							}

							function remove(id) {
							    return (elem=document.getElementById(id)).parentNode.removeChild(elem);
							}

							remove('_stack');
						</script>
					";
			}

			flush();
			ob_flush();
		}
