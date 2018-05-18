<?php
class Mailer
{
	private $encoding = null;
	private $recipients = null;
	private $subject = "";
	private $from = "";
	private $fromname = "";
	private $content = null;
	private $files = null;
	private $boundary = null;
	private $sender = null;
	private $cc = null;
	private $headers = null;

	public function __construct()
	{
		$this->encoding = mb_internal_encoding();
		$this->recipients = array();
		$this->subject = "(no subject)";
		$this->content = array();
		$this->content["text"] = "";
		$this->content["html"] = "";
		$this->files = array();
		$this->boundary = md5(microtime());
		$this->cc = array();
		$this->bcc = array();
		$this->headers = array();
	}

	public function setEncoding($encoding)
	{
		$this->encoding = $encoding;
	}

	public function addRecipient($to)
	{
		if(!$to)
		{
			return false;
		}
		$this->recipients[] = trim($to);
		return true;
	}

	public function addCc($cc)
	{
		if(!$cc)
		{
			return false;
		}
		$this->cc[] = $cc;
		return true;
	}

	public function addBcc($bcc)
	{
		if(!$bcc)
		{
			return false;
		}
		$this->bcc[] = $bcc;
		return true;
	}

	public function addHeader($header)
	{
		$this->header[] = $header;
	}

	public function setSubject($subject = "")
	{
		$subject = trim($subject);
		if(!$subject)
		{
			$subject = "(no subject)";
		}
		$this->subject = $subject;
		return true;
	}

	public function setFrom($from = null, $fromname = "")
	{
		$from = trim($from);
		if(!$from)
		{
			return false;
		}
		$fromname = trim($fromname);

		$this->from = $from;
		$this->fromname = ($fromname ? $fromname : $from);
		return true;
	}

	public function setText($content = "")
	{
		$this->content["text"] = $content;
	}

	public function setHtml($content = "")
	{
		$content = trim($content);
		$this->content["html"] = $content;
	}

	public function addFile($filename, $data = null, $mimetype = null, $zip = false)
	{
		// TODO: to zip...
		$filename = trim($filename);
		if($data === null)
		{
			if(file_exists($filename))
			{
				$data = file_get_contents($filename);
			}
		}

		$file = array();
		$file["name"] = basename($filename);
		$file["data"] = $data;
		$file["mime"] = ($mimetype ? $mimetyep : "application/octet-stream");
		$this->files[] = $file;

		return true;
	}

	public function setSender($address = null)
	{
		if(!$address)
		{
			return false;
		}
		$this->sender = $address;
	}

	private function _text($header = false)
	{
		$body = chunk_split(base64_encode($this->content["text"]));
		if($header)
		{
			$content = array();
			$content[] = "Content-Type: text/plain; charset=" . $this->encoding;
			$content[] = "Content-Transfer-Encoding: base64";
			$content[] = "";
			$content[] = $body;

			return implode("\r\n", $content);
		}
		else
		{
			return $body;
		}
	}

	private function _html($header = false)
	{
		$body = chunk_split(base64_encode($this->content["html"]));
		if($header)
		{
			$content = array();
			$content[] = "Content-Type: text/html; charset=" . $this->encoding;
			$content[] = "Content-Transfer-Encoding: base64";
			$content[] = "";
			$content[] = $body;

			return implode("\r\n", $content);
		}
		else
		{
			return $body;
		}
	}

	private function _alternative($header = false)
	{
		$content = array();
		$boundary = $this->boundary;
		if($header)
		{
			$boundary = md5(uniqid(md5($this->content["text"]), true));
			$content[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
			$content[] = "Content-Transfer-Encoding: 7bit";
			$content[] = "";

		}
		$content[] = "--" . $boundary;
		$content[] = $this->_text(true);
		$content[] = "--" . $boundary;
		$content[] = $this->_html(true);
		$content[] = "--" . $boundary . "--";
		if($header)
		{
			$content[] = "";
		}
		return implode("\r\n", $content);
	}

	private function _mixed()
	{
		$content = array();

		if($this->content["text"] && $this->content["html"])
		{
			$content[] = "--" . $this->boundary;
			$content[] = $this->_alternative(true);
		}
		elseif($this->content["html"])
		{
			$content[] = "--" . $this->boundary;
			$content[] = $this->_html(true);
		}
		else
		{
			$content[] = "--" . $this->boundary;
			$content[] = $this->_text(true);
		}
		foreach($this->files as $file)
		{
			$content[] = "--" . $this->boundary;
			$content[] = "Content-Type: " . $file["mime"];
			$content[] = "Content-Transfer-Encoding: base64";
			$content[] = "Content-Disposition: attachment; filename=" . sprintf('"%s"', $file["name"]);
			$content[] = "";
			$content[] = chunk_split(base64_encode($file["data"]));
		}
		$content[] = "--" . $this->boundary . "--";
		return implode("\r\n", $content);
	}

	public function send()
	{
		if(!$this->recipients)
		{
			return false;
		}
		if(!$this->from)
		{
			return false;
		}

		$to = implode(",", $this->recipients);

		$subject = "=?" . $this->encoding . "?B?" . base64_encode($this->subject) . "?=";
		$content = base64_encode($content);
		$fromname = "=?" . $this->encoding . "?B?" . base64_encode($this->fromname) . "?=";

		$headers = [];
		$headers[] = sprintf("From: %s <%s>", $fromname, $this->from);
		if(!empty($this->cc))
		{
			$headers[] = sprintf("Cc: %s", implode(",", $this->cc));
		}
		if(!empty($this->bcc))
		{
			$headers[] = sprintf("Bcc: %s", implode(",", $this->bcc));
		}
		$headers[] = "MIME-Version: 1.0";
		foreach($this->headers as $header)
		{
			$headers[] = $header;
		}

		if(0 < count($this->files))
		{
			$headers[] = 'Content-Type: multipart/mixed; boundary="' . $this->boundary . '"';
			$headers[] = "Content-Transfer-Encoding: 7bit";
			$content = $this->_mixed();
		}
		elseif(empty($this->content["html"]))
		{
			$headers[] = "Content-Type: text/plain; charset=" . $this->encoding;
			$headers[] = "Content-Transfer-Encoding: base64";
			$content = $this->_text();
		}
		elseif(empty($this->content["text"]))
		{
			$headers[] = "Content-Type: text/html; charset=" . $this->encoding;
			$headers[] = "Content-Transfer-Encoding: base64";
			$content = $this->_html();
		}
		else
		{
			$headers[] = 'Content-Type: multipart/alternative; boundary="' . $this->boundary . '"';
			$headers[] = "Content-Transfer-Encoding: 7bit";
			$content = $this->_alternative();
		}

		$option = null;
		if($this->sender)
		{
			$option = "-f" . $this->sender;
		}
		$result = mail($to, $subject, $content, implode("\r\n", $headers), $option);
		return $result;
	}

	public function text($to = null, $subject = '', $content = '', $from = '', $fromname = '')
	{
		return $this->html($to, $subject, $content, "", $from, $fromname);
	}

	public function html($to = null, $subject = '', $text = '', $html = '', $from = '', $fromname = '')
	{
		if(!is_array($to))
		{
			$to = explode(",", $to);
		}

		foreach($to as $recipient)
		{
			if(!$this->addRecipient($recipient))
			{
				return false;;
			}
		}

		if(!$this->setfrom($from, $fromname))
		{
			return false;
		}

		if(!$this->setSubject($subject))
		{
			return false;
		}

		$this->setText($text);
		$this->setHtml($html);

		if(!$this->send())
		{
			return false;
		}
		return true;
	}
}