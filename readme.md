Easy form creation + validation (requires formo):  

```php
$contactForm = new FormGen(array(
	'name' => array(),
	'email' => array(),
	'phone' => array(),
	'home_zip_code' => array(),
	'best_way_to_contact' => array(
		'type' => 'radio',
		'values' => array('Email', 'Phone')
	),
	'subject' => array(
		'type' => 'select',
		'values' => array(
			"General Inquiry" => "general_inquiry",
			"Quote Request" => "quote_request",
			"Quote Request With Picture Upload" => "quote_request_picture"
		)
	),
	'picture_upload' => array(
		'type' => 'file',
		'upload_path' => 'uploads',
		'required' => false
	),
	'upload_instructions' => array(
		'type' => 'custom',
		'label' => '<div id="upload_instructions" class="notice">&nbsp;</div>'
	),
	'message' => array(
		'type' => 'textarea'
	),
));

$contactForm->form_class = 'standard';
$contactForm->form_style = "width:60%";

if($contactForm->process()) {
	$message = $contactForm->generate_email_message();
	$result = email::send('your@email.com', $contactForm->objectReference->email, "Subject", $message);
	$this->template->content = "<h1>Thank You!</h1>";
} else {		
	$this->template->content = new View('contact', array(
		'form' => $contactForm->generate()
	));
}
```

Notes  
 * When using FormGen, and using the formo mselect module, be sure to include [] after the element name