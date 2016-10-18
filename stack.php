<?php
	Abstract Class Stack
	{
		Public Static $_LOGS = "";
		Public Static $_ERROR = "";
		Public Static $_WARN = "";
		Public Static $_INFO = "";
		Public Static $_DEBUG = true;

		Public Static Function error(
			$errno,
			$errstr,
			$errfile,
			$errline
		)
		{
			$error = array(
				"id" => $errno,
				"message" => $errstr,
				"file" => $errfile,
				"line" => $errline
			);

			switch($errno)
			{
				case E_NOTICE:
				case E_WARNING:
				case E_USER_WARNING:
					self::$_WARN[] = $error;
				break;

				case E_USER_NOTICE:
					self::$_INFO[] = $error;
				break;

				default:
					if(!empty($errstr))
						self::$_ERROR[] = $error;
			}
		}

		Public Static Function fatal($exception)
		{
			if(empty($exception)) return false;

			self::$_ERROR[] =
			$error = (
					is_array($exception)
				?	array(
						"id" => 0,
						"message" => $exception['message'],
						"file" => $exception['file'],
						"line" => $exception['line'],
						"type" => "FATAL"
					)
				:	array(
						"id" => 0,
						"message" => $exception->getMessage(),
						"file" => $exception->getFile(),
						"line" => $exception->getLine(),
						"type" => "EXCEPTION"
					)
			);

			ob_end_clean();
			echo 	$error['type'].": ".$error['message']." <br>".
					"LINE: ".$error['line']."<br>".
					"FILE: ".$error['file']."<br>".
					"BACKTRACE:<pre>".print_r(debug_backtrace(), true)."</pre>";
		}

		Public Static Function shutdown()
		{
			self::fatal(error_get_last());

			echo "
				<script id='_stack'>
					if (!window.console)
						throw new Error('This browser does not support console!');

					// history.replaceState({}, '', '/');
					// history.replaceState({}, '', window.location.href);
			";

			// SERVER
				$table_server = "";
				foreach ($_SERVER as $key => $value)
					$table_server .= "'".$key."':{'value': '".self::fix($value)."'},";
				echo "
					if(window.self === window.top)
					{
						console.groupCollapsed('SERVER');
						console.table({".rtrim($table_server, ",")."});
						console.groupEnd();
					}
				";

			// PAGE
				echo "
					console.group('"
					.rtrim(basename($_SERVER['PHP_SELF']), ".php")
					." ".round((microtime(true) - $_SERVER['REQUEST_TIME']), 2)."s"
					." ".self::convertMemory(memory_get_usage(true))
					."');
				";

				// REQUEST
					if(!isset($_SESSION)) session_start();
					$request = array(
						"GET" => $GLOBALS["_GET"],
						"POST" => $GLOBALS["_POST"],
						"SESSION" => $GLOBALS["_SESSION"],
						"LOGS" => self::$_LOGS//array_merge($GLOBALS['_LOGS'], self::$_LOGS)
					);
					echo "console.groupCollapsed('REQUEST');";
					foreach($request as $request_key => $request_value)
					{
						if(!empty($request_value))
						{
							echo "
								console.groupCollapsed('".$request_key."');
								console.log(".json_encode($request_value)." );
								console.groupEnd();
							";
						}
					}
					echo "console.groupEnd();";

				// ERRORS
					$error_types = array(
						"WARNINGS" => "WARN",
						"ERRORS" => "ERROR",
						"INFO" => "INFO",
					);
					foreach($error_types as $error_key => $error_type)
					{
						if(!is_array(self::${"_".$error_type}))
							continue;

						self::${"_".$error_type} = array_unique(
							@(array)self::${"_".$error_type},
							SORT_REGULAR
						);
						echo "console.group".($error_key == "WARNINGS" ? "Collapsed" : "")."('".$error_key." (".count(self::${"_".$error_type}).")');";
						foreach (self::${"_".$error_type} as $key => $value)
							echo 	"console.".strtolower($error_type)
									."('FILE:\\t".self::fix($value['file'])
									."\\nLINE:\\t".$value['line']
									."\\nMSG:\\t".self::fix($value['message'])."');";
						echo "console.groupEnd();";
					}

			echo "
					console.groupEnd();
				</script>
			";

			ob_flush(); flush();
		}

		Public Static Function run()
		{
			ini_set("display_errors", true);
			ini_set("html_errors", false);
			error_reporting(E_ALL);

			set_error_handler("Stack::error");
			set_exception_handler('Stack::fatal');

			if 	(
						!isset($_SERVER['HTTP_X_REQUESTED_WITH'])// xmlhttprequest
					&& 	self::$_DEBUG
				)
			{
				header("Content-Type: text/html");
				ob_start();
				register_shutdown_function("Stack::shutdown");
			}
		}

		Public Static Function log(
			$value,
			$key = ""
		)
		{
			if(empty($key))
				self::$_LOGS[] = $value;
			else
				self::$_LOGS[$key] = $value;

			return $value;
		}

		// helper functions
			Private Static Function fix($message)
			{
				return 		!is_array($message)
						?	str_replace(
								array(
									"'",
									"\\",
									"\n",
									"\r",
								),
								array(
									"`",
									"/",
									"\\n",
									"\\r",
								),
								$message
							)
						:	$message;
			}

			Private Static Function convertMemory($size)
			{
				$unit = array('B','KB','MB','GB','TB','PB');
				return @round($size/pow(1024*8,($i=floor(log($size,1024*8)))),2).' '.$unit[$i];
			}
	}

	Stack::run();
