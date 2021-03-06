<?php

/**
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 * @package Yii2Debug
 * @since 1.1.13
 */
class Yii2RequestPanel extends Yii2DebugPanel
{
	public function getName()
	{
		return 'Request';
	}

	public function getSummary()
	{
		$url = $this->getUrl();

		$status = '';
		if ($statusCode = $this->data['statusCode']) {
			if ($statusCode >= 200 && $statusCode < 300) {
				$class = 'label-success';
			} elseif ($statusCode >= 100 && $statusCode < 200) {
				$class = 'label-info';
			} else {
				$class = 'label-important';
			}
			$status .= <<<HTML
<div class="yii2-debug-toolbar-block">
	<a href="$url" title="Status code: $statusCode">Status <span class="label $class">$statusCode</span></a>
</div>
HTML;
		}

		return $status . <<<HTML
<div class="yii2-debug-toolbar-block">
	<a href="$url">Action <span class="label">{$this->data['action']}</span></a>
</div>
HTML;
	}

	public function getDetail()
	{
		$data = array(
			'Route' => $this->data['route'],
			'Action' => $this->data['action'],
			'Parameters' => $this->data['actionParams'],
		);
		return $this->renderTabs(array(
			array(
				'label' => 'Parameters',
				'content' => $this->renderDetail('Routing', $data)
					. $this->renderDetail('$_GET', $this->data['GET'])
					. $this->renderDetail('$_POST', $this->data['POST'])
					. $this->renderDetail('$_FILES', $this->data['FILES'])
					. $this->renderDetail('$_COOKIE', $this->data['COOKIE']),
				'active' => true,
			),
			array(
				'label' => 'Headers',
				'content' => $this->renderDetail('Request Headers', $this->data['requestHeaders'])
					. $this->renderDetail('Response Headers', $this->data['responseHeaders']),
			),
			array(
				'label' => 'Session',
				'content' => $this->renderDetail('$_SESSION', $this->data['SESSION'])
					. $this->renderDetail('Flashes', $this->data['flashes']),
			),
			array(
				'label' => '$_SERVER',
				'content' => $this->renderDetail('$_SERVER', $this->data['SERVER']),
			),
		));
	}

	public function save()
	{
		if (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
		} elseif (function_exists('http_get_request_headers')) {
			$requestHeaders = http_get_request_headers();
		} else {
			$requestHeaders = array();
		}
		$responseHeaders = array();
		foreach (headers_list() as $header) {
			if (($pos = strpos($header, ':')) !== false) {
				$name = substr($header, 0, $pos);
				$value = trim(substr($header, $pos + 1));
				if (isset($responseHeaders[$name])) {
					if (!is_array($responseHeaders[$name])) {
						$responseHeaders[$name] = array($responseHeaders[$name], $value);
					} else {
						$responseHeaders[$name][] = $value;
					}
				} else {
					$responseHeaders[$name] = $value;
				}
			} else {
				$responseHeaders[] = $header;
			}
		}

		$route = Yii::app()->getUrlManager()->parseUrl(Yii::app()->getRequest());
		$action = null;
		$actionParams = array();
		if (($ca = Yii::app()->createController($route)) !== null) {
			/* @var CController $controller */
			/* @var string $actionID */
			list($controller, $actionID) = $ca;
			if (!$actionID) $actionID = $controller->defaultAction;
			if (($a = $controller->createAction($actionID)) !== null) {
				if ($a instanceof CInlineAction) {
					$action = get_class($controller) . '::action' . ucfirst($actionID) . '()';
				} else {
					$action = get_class($a) . '::run()';
				}
			}
			$actionParams = $controller->actionParams;
		}

		/* @var CWebUser $user */
		$user = Yii::app()->getComponent('user', false);

		return array(
			'flashes' => $user ? $user->getFlashes(false) : array(),
			'statusCode' => $this->getStatusCode(),
			'requestHeaders' => $requestHeaders,
			'responseHeaders' => $responseHeaders,
			'route' => $route,
			'action' => $action,
			'actionParams' => $actionParams,
			'SERVER' => empty($_SERVER) ? array() : $_SERVER,
			'GET' => empty($_GET) ? array() : $_GET,
			'POST' => empty($_POST) ? array() : $_POST,
			'COOKIE' => empty($_COOKIE) ? array() : $_COOKIE,
			'FILES' => empty($_FILES) ? array() : $_FILES,
			'SESSION' => empty($_SESSION) ? array() : $_SESSION,
		);
	}

	private $_statusCode;

	/**
	 * @return int|null
	 */
	protected function getStatusCode()
	{
		if (function_exists('http_response_code')) {
			return http_response_code();
		} else {
			return $this->_statusCode;
		}
	}

	public function __construct()
	{
		if (!function_exists('http_response_code')) {
			Yii::app()->attachEventHandler('onException', array($this, 'onException'));
		}
	}

	/**
	 * @param CExceptionEvent $event
	 */
	protected function onException($event)
	{
		if ($event->exception instanceof CHttpException) {
			$this->_statusCode = $event->exception->statusCode;
		} else {
			$this->_statusCode = 500;
		}
	}
}