<?php
if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
 }

require_once dirname(__DIR__) . '/dompdf/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class PluginProtocolsmanagerGenerate extends CommonDBTM {
	
		function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
			return self::createTabEntry('Protocols manager');
		}

		static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
			global $DB, $CFG_GLPI;
			
			$tab_access = self::checkRights();
		
			if ($tab_access == 'w') {
				$PluginProtocolsmanagerGenerate = new self();
				$PluginProtocolsmanagerGenerate->showContent($item);
			} else {
				echo "<div align='center'><br><img src='".$CFG_GLPI['root_doc']."/pics/warning.png'><br>".__("Access denied")."</div>";
			}
		}
		
		//check if logged user have rights to plugin
		static function checkRights() {
			global $DB;
			$active_profile = $_SESSION['glpiactiveprofile']['id'];
			$req = $DB->request('glpi_plugin_protocolsmanager_profiles',
			['profile_id' => $active_profile]);
			
			if($row = $req->current()) {
				$tab_access = $row['tab_access'];
			}
			else{
				$tab_access = "";
			}

			return $tab_access;
	
		}
		
		//show plugin content
		function showContent($item) {
			global $DB, $CFG_GLPI;
			
			$id = $item->getField('id');
			$type_user   = $CFG_GLPI['linkuser_types'];
			$field_user  = 'users_id';
			$rand = mt_rand();
			$counter = 0;

			echo "<form method='post' name='user_field".$rand."' id='user_field".$rand."' action=\"" . $CFG_GLPI["root_doc"] . "/plugins/protocolsmanager/front/generate.form.php\">";
			echo "<table class='tab_cadre_fixe'><tr><td style ='width:25%'></td>";
			echo "<td class='center' style ='width:25%'>";
			echo "<select required name='list' style='font-size:14px; width:95%'>";
				foreach ($doc_types = $DB->request('glpi_plugin_protocolsmanager_config',
				['FIELDS' => ['glpi_plugin_protocolsmanager_config' => ['id', 'name']]]) as $uid => $list) {
					echo '<option value="';
					echo $list["id"];
					echo '">';
					echo $list["name"];
					echo '</option>';
				}
			echo "</select></td>";
			echo "<td style='width:10%'><input type='submit' name='generate' class='submit' value='".__('Create')."'></td>";
			echo "<td style='width:30%'></td></tr>";
			echo "<tr><td></td><td colspan='2'><input type='text' name='notes' placeholder='".__('Note')."' style='width:89%; font-size:14px; padding: 2px'></td><td></td></tr>";
			echo "</table>";

			// Premier tableau
			echo "<div class='spaced'><table class='tab_cadre_fixehov' id='additional_table'>";
			$header = "<th width='10'><input type='checkbox' class='checkall' style='height:16px; width: 16px;'></th>";
			$header .= "<th>".__('Type')."</th>";
			$header .= "<th>".__('Manufacturer')."</th>";
			$header .= "<th>".__('Model')."</th>";
			$header .= "<th>".__('Name')."</th>";
			$header .= "<th>".__('Serial number')."</th>";
			$header .= "<th>".__('Inventory number')."</th>";
			$header .= "<th>".__('Comments')."</th></tr>";
			echo $header;
			
				foreach ($type_user as $itemtype) {
					if (!($item = getItemForItemtype($itemtype))) {
						continue;
					}
					if ($item->canView()) {

						// il va récupérer toutes les tables du matos
						$itemtable = getTableForItemType($itemtype);
						
						$iterator_params = "SELECT *
						FROM $itemtable
						WHERE $field_user = $id";

						if ($item->maybeTemplate()) {
							// j'ai du mettre un espace après le " et le and car sinon dans la requete les deux sont collés et ça casse la requête
							$iterator_params .= " AND is_template = 0";
						}
						
						if ($item->maybeDeleted()) {
							$iterator_params .= " AND is_deleted = 0";
						}

						$item_iterator = $DB->request($iterator_params);
						$type_name = $item->getTypeName();
						$item_iterator->current();

						foreach ($item_iterator as $data) {
								$cansee = $item->can($data["id"], READ);
								(empty($data["name"])) ? ($link = $data["id"]) : ($link  = $data["name"]);
								if ($cansee) {
									$link_item = $item::getFormURLWithID($data['id']);
									if ($_SESSION["glpiis_ids_visible"] || empty($link)) {
										$link = sprintf(__('%1$s (%2$s)'), $link, $data["id"]);
									}
									$link = "<a href='".$link_item."'>".$link."</a>";
								}
								$linktype = "";
								if ($data[$field_user] == $id) {
									$linktype = self::getTypeName(1);
								}
								
								echo "<tr class='tab_bg_1'>";
								echo "<td width='10'>";
								echo "<input type='checkbox' name='number[]' value='$counter' class='child' style='height:16px; width: 16px;'>";
								echo "</td>";
								echo "<td class='center'>$type_name</td>";
								echo "<td class='center'>";
								
								if (isset($data["manufacturers_id"]) && !empty($data["manufacturers_id"])) {
									
									$man_id = $data["manufacturers_id"];
									
									$req = $DB->request(
										'glpi_manufacturers',
										['id' => $man_id ]);
																			
									if ($row = $req->current()) {
										$man_name = $row["name"];
									}
									
									$man_name = explode(' ',trim($man_name))[0];
									echo $man_name;
								}
								else {
									echo '&nbsp;';
									$man_name = '';
								}
								echo "</td>";
								echo "<td class='center'>";

									$modeltypes = ["computer", "phone", "monitor", "networkequipment", "printer", "peripheral"];
									$mod_name = '';
									
									foreach($modeltypes as $prefix) {
										if(isset($data[$prefix.'models_id']) && !empty($data[$prefix.'models_id'])) {
											$mod_id = $data[$prefix.'models_id'];

											$req2 = $DB->request(
												'glpi_'.$prefix.'models',
												['id' => $mod_id ]);
 
											if ($row2 = $req2->current()) {
												$mod_name = $row2["name"];
											}
											
											else {
												echo '&nbsp;';
												$mod_name = '';
											}	
											echo $mod_name;
										}

									}
								echo "</td>";
								echo "<td class='center'>$link</td>";
								echo "<td class='center'>";
								
								if (isset($data["serial"]) && !empty($data["serial"])) {
									$serial = $data["serial"];
									echo $serial;
								} else {
									echo '&nbsp;';
									$serial = '';
								}
								
								echo "</td>";
								echo "<td class='center'>";
								
								if (isset($data["otherserial"]) && !empty($data["otherserial"])) {
									$otherserial = $data["otherserial"];
									echo $otherserial;
								} else {
									echo '&nbsp;';
									$otherserial = '';
								}
								
								echo "</td>";
								
								if (isset($data["name"]) && !empty($data["name"])) {
									$item_name = $data["name"];
								}
								else {
									echo '&nbsp;';
									$item_name = '';
								}
								
								$Owner = new User();
								$Owner->getFromDB($id);
								$Author = new User();
								$Author->getFromDB(Session::getLoginUserID());
								// il faut get le template utilisé
								// getrawname remplacé https://github.com/glpi-project/glpi/blob/10.0/bugfixes/CHANGELOG.md
								// il y avait aussi getRawName() mais je suis pas sur que ça soit vu le changelog
								$owner = $Owner->getFriendlyName();
								$author = $Author->getFriendlyName();
																
								echo "<input type='hidden' name='owner' value ='$owner'>";
								echo "<input type='hidden' name='author' value ='$author'>";
								echo "<input type='hidden' name='type_name[]' value='$type_name'>";
								echo "<input type='hidden' name='man_name[]' value='$man_name'>";
								echo "<input type='hidden' name='mod_name[]' value='$mod_name'>";
								echo "<input type='hidden' name='serial[]' value='$serial'>";
								echo "<input type='hidden' name='otherserial[]' value='$otherserial'>";
								echo "<input type='hidden' name='item_name[]' value='$item_name'>";
								echo "<input type='hidden' name='user_id' value='$id'>";
								
								echo "<td class='center'><input type='text' name='comments[]'></td>";
								echo "</tr>";
								
								
							$counter++;
						}
						
					}
					
				}

				
				echo "</table>";
				Html::closeForm();
				echo "</div>";
				
				
				//send email popup

				$conca  = '<div class="modal fade" id="motus" role="dialog">';
				$conca .= '<div class="modal-dialog">';
				$conca .= '<div class="modal-content">';
				$conca .= '<div class="modal-header">';
				$conca .= '<h4 class="modal-title">'.__("Send").' email</h4>';
				$conca .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>';
				$conca .= '</div><div class="modal-body" title="'.__("Send").' email"><p>Select recipients from template or enter manually to send email</p><br><br>';
				$conca .= '<form method="post" action="'.$CFG_GLPI["root_doc"].'/plugins/protocolsmanager/front/generate.form.php">';
				$conca .= '<input type="hidden" id="dialogVal" name="doc_id" value="">';
				$conca .= '<input type="radio" name="send_type" id="manually" class="send_type" value="1"><b> Enter recipients manually </b><br><br>';
				$conca .= '<textarea style="width:90%; height:30px" name="em_list" class="man_recs" placeholder="Recipients (use ; to separate emails)"></textarea><br><br>';
				$conca .= '<input type="text" style="width:90%" name="email_subject" class="man_recs" placeholder="Subject"><br><br>';
				$conca .= '<textarea style="width:90%; height:80px" name="email_content" class="man_recs" placeholder="Content"></textarea><br><br>';
				$conca .= '<input type="radio" name="send_type" id="auto" class="send_type" value="2"><b> Select recipients from template</b><br><br>';

				$conca .= '<select name="e_list" id="auto_recs" disabled="disabled" style="font-size:14px; width:95%">';

				foreach ($DB->request('glpi_plugin_protocolsmanager_emailconfig') as $uid => $list) {
					$conca .= '<option value="';
					$conca .= $list["recipients"]."|".$list["email_subject"]."|".$list["email_content"]."|".$list["send_user"];
					$conca .= '">';
					$conca .= $list["tname"]." - ".$list["recipients"];
					$conca .= '</option>';
				}
				$conca .= '</select><br><br><input type="submit" name="send" class="submit" value='.__("Send").'>';

				if(!empty($author))
				{
					$conca .= '<input type="hidden" name="author" value="'.$author.'">';
				}

				if(!empty($owner))
				{
					$conca .= '<input type="hidden" name="owner" value="'.$owner.'">';
				}
				
				$conca .= '</form>';
				// Html::closeForm();

				$conca .= '</div>';
				$conca .= '</div>';
				$conca .= '</div>';
				$conca .= '</div>';
				$conca .= '</div>';
				echo $conca;
				// fin du popup
				
				//add custom row
				echo "<div class='spaced'><button class='addNewRow' id='addNewRow' style='background-color:#8ec547; color:#fff; cursor:pointer; font:bold 12px Arial, Helvetica; border:0; padding:5px;'>Add Custom Fields</button></div>";
				
				echo "<div class='spaced'>";
				echo "<form method='post' name='docs_form' action='".$CFG_GLPI["root_doc"]."/plugins/protocolsmanager/front/generate.form.php'>";
				echo "<table class='tab_cadre_fixe'><td style='width:5%'><img src='".$CFG_GLPI["root_doc"]."/plugins/protocolsmanager/img/arrow-left-top.png'></td><td style='width:5%'>";
				echo "<input type='submit' name='delete' class='submit' value=".__('Delete').">";
				echo "</td><td style='width:90%'></table>";
				echo "<table class='tab_cadre_fixehov' id='myTable'>";
				echo "<th width='10'><input type='checkbox' class='checkalldoc' style='height:16px; width: 16px;'></th>";
				$header2 = "<th>".__('Name')."</th>";
				$header2 .= "<th>".__('Type')."</th>";
				$header2 .= "<th>".__('Date')."</th>";
				$header2 .= "<th>".__('File')."</th>";
				$header2 .= "<th>".__('Creator')."</th>";
				$header2 .= "<th>".__('Comment')."</th>";
				$header2 .= "<th>".__('Send email')."</th></tr>";
				echo $header2;

				self::getAllForUser($id);
				echo "</table>";
				Html::closeForm();
				echo "</div>";
				
				return true;
			
		}
		
		// TODO
		//show user's generated documents
		static function getAllForUser($id) {
			global $DB, $CFG_GLPI;
			
			$exports = [];
			$doc_counter = 0;
			
			foreach ($DB->request(
				'glpi_plugin_protocolsmanager_protocols',
				['user_id' => $id ]) as $export_data => $exports) {
					
					
					echo "<tr class='tab_bg_1'>";
					
					echo "<td class='center'>";
					echo "<input type='checkbox' name='docnumber[]' value='".$exports['document_id']."' class='docchild' style='height:16px; width: 16px;'>";
					echo "</td>";
					
					echo "<td class='center'>";
					$Doc = new Document();
					$Doc->getFromDB($exports['document_id']);
					echo $Doc->getLink();
					echo "</td>";
					
					echo "<td class='center'>";
					echo $exports['document_type'];
					echo "</td>";
					
					echo "<td class='center'>";
					echo $exports['gen_date'];
					echo "</td>";
					
					echo "<td class='center'>";
					echo $Doc->getDownloadLink();
					echo "</td>";
					
					echo "<td class='center'>";
					echo $exports['author'];
					echo "</td>";
					
					echo "<td class='center'>";
					echo $Doc->getField("comment");
					echo "</td>";
					
					echo "<td class='center'>";
					echo "<span class='docid' style='display:none'>".$exports['document_id']."</span>";
					echo "<a class='openDialog' style='background-color:#8ec547; color:#fff; cursor:pointer; font:bold 12px Arial, Helvetica; border:0; padding:5px;' href='#'>".__('Send')."</a>";
					echo "</td>";
					
					
					echo "</tr>";

					$doc_counter++;
				}
		}
		
		//make PDF and save to DB
		static function makeProtocol() 
		{
			global $DB, $CFG_GLPI;

				$number = $_POST['number'];
				$type_name = $_POST['type_name'];
				$man_name = $_POST['man_name'];
				$mod_name = $_POST['mod_name'];
				$serial = $_POST['serial'];
				$otherserial = $_POST['otherserial'];
				$item_name = $_POST['item_name'];
				$owner = $_POST['owner'];
				$author = $_POST['author'];				
				
				$doc_no = $_POST['list'];
				$id = $_POST['user_id'];
				$notes = $_POST['notes'];
				
				$prot_num = self::getDocNumber();

				$req = $DB->request(
					'glpi_plugin_protocolsmanager_config',
					['id' => $doc_no ]);
					
				if ($row = $req->current()) {
					$content = nl2br($row["content"]);
					$content = str_replace("{cur_date}", date("d.m.Y"), $content);
					$content = str_replace("{owner}", $owner, $content);
					$content = str_replace("{admin}", $author, $content);
					$upper_content = nl2br($row["upper_content"]);
					$upper_content = str_replace("{cur_date}", date("d.m.Y"), $upper_content);
					$upper_content = str_replace("{owner}", $owner, $upper_content);
					$upper_content = str_replace("{admin}", $author, $upper_content);
					$footer = nl2br($row["footer"]);
					$title = $row["title"];
					$title = str_replace("{owner}", $owner, $title);
					$title_template = $row["name"];
					$full_img_name = $row["logo"];
					$font = $row["font"];
					$fontsize = $row["fontsize"];
					$city = $row["city"];
					$serial_mode = $row["serial_mode"];
					$orientation = $row["orientation"];
					$breakword = $row["breakword"];
					$email_mode = $row["email_mode"];
					$email_template = $row["email_template"];
					$author_name = $row["author_name"];
					$author_state = $row["author_state"];
				}

				$req2 = $DB->request(
					'glpi_plugin_protocolsmanager_emailconfig',
					['id' => $email_template ]);
				
				if ($row2 = $req2->current()) {
					$send_user = $row2["send_user"];
					$email_subject = $row2["email_subject"];
					$email_content = $row2["email_content"];
					$recipients = $row2["recipients"];
				}
				
				$comments = $_POST['comments'];
				
				if (!isset($font) || empty($font)) {
					$font = 'dejavusans';
				}
	
				if (!isset($fontsize) || empty($fontsize)) {
					$fontsize = '9';
				}
				
				if (!isset($city) || empty($city)) {
					$city = '';
				}
				
				if (!isset($email_content) || empty($email_content)) {
					$email_content = '';
				}
				$email_content = str_replace("{owner}", $owner, $email_content);
				$email_content = str_replace("{admin}", $author, $email_content);
				$email_content = str_replace("{cur_date}", date("d.m.Y"), $email_content);
				
				if (!isset($email_subject) || empty($email_subject)) {
					$email_subject = '';
				}
				
				$email_subject = str_replace("{owner}", $owner, $email_subject);
				$email_subject = str_replace("{admin}", $author, $email_subject);
				$email_subject = str_replace("{cur_date}", date("d.m.Y"), $email_subject);
	
				if (!isset($recipients) || empty($recipients)) {
					$recipients = '';
				}
				
				//change margin if no image
				if (!isset($full_img_name) || empty($full_img_name)) {
					$backtop = "20mm";
					$islogo = 0;
				} else {
					$logo = GLPI_ROOT.'/files/_pictures/'.$full_img_name;
					$backtop = "40mm";
					$islogo = 1;
				}
				
				// debut buffer
				// $file_content ="";
				ob_start();

				include dirname(__FILE__).'/template.php';

				// var_dump($file_content);
				$file_content = ob_get_contents();

				// clean le buffer, il y'a plusieurs layers
				// cf https://stackoverflow.com/questions/10352964/php-buffer-doesnt-stop-after-ob-end-clean
				while (@ob_end_clean()) {  
					// do nothing   
				}


				// echo ob_get_level();
				// echo $file_content;
				// var_dump($file_content);

				$options = new Options();
				$options -> set('defaultFont', $font);

				$html2pdf = new Dompdf($options);
				$html2pdf->loadHtml($file_content);
				$html2pdf->setPaper('A4', $orientation);
				$html2pdf->render();

				$doc_name = str_replace(' ', '_', $title)."-".date('dmY').'.pdf';
				$output = $html2pdf->output();

				file_put_contents(GLPI_UPLOAD_DIR .'/'.$doc_name, $output);
				
				$doc_id = self::createDoc($doc_name, $notes, $id);
				
				if ($email_mode == 1) {
					
					self::sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id);
					
				}
				
				$gen_date = date('Y-m-d H:i:s');
				// var_dump($title);
				// die();
				$DB->insert('glpi_plugin_protocolsmanager_protocols', [
					'name' => $doc_name,
					'gen_date' => $gen_date,
					'author' => $author,
					'user_id' => $id,
					'document_id' => $doc_id,
					'document_type' => $title_template
					]
				);
			
		}
		
		static function getDocNumber() {
			global $DB;
			
			$req = $DB->request('SELECT MAX(id) as max FROM glpi_plugin_protocolsmanager_protocols');

			if ($row = $req->current()) {
				$nextnum = $row["max"];
				if (!$nextnum) {
					return 1;
				}
				else {
					$nextnum++;
					return $nextnum;
				}
			}
		}
		
		//create GLPI document
		static function createDoc($doc_name, $notes, $id) {
			global $DB, $CFG_GLPI;

			$req = $DB->request(
				'glpi_users',
				['id' => $id ]);	
			
			if ($row = $req->current()) {
				$entity = $row["entities_id"];
			}

			if (!Session::haveAccessToEntity($entity)) {
				$entity = Session::getActiveEntity();
			}
			
			$input = [];
			$doc = new Document();
			$input["entities_id"] = $entity;
			$input["name"] = date('mdY_Hi');
			$input["upload_file"] = $doc_name;
			$input["documentcategories_id"] = 0;
			$input["mime"] = "application/pdf";
			$input["date_mod"] = date("Y-m-d H:i:s");
			$input["users_id"] = Session::getLoginUserID();
			$input["comment"] = $notes;
			$doc->check(-1, CREATE, $input);
			$document_id = $doc->add($input);
			return $document_id;
		}
		
		//delete selected documents
		static function deleteDocs() {
			global $DB, $CFG_GLPI;
			
			$docnumber = $_POST['docnumber'];
			
			foreach ($docnumber as $del_key) {
				
				$DB->delete(
					'glpi_plugin_protocolsmanager_protocols', [
						'document_id' => $del_key
					]
				);
				
				$doc = new Document();
				$doc->getFromDB($del_key);
				$doc->delete(['id' => $del_key], true);
			}
		}
		
		//send mail notification
		static function sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id) {
			
			global $CFG_GLPI, $DB;
			$nmail = new GLPIMailer();
			
			$nmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
			
			$recipients_array = explode(';',$recipients);
			
			$req = $DB->request(
				'glpi_documents',
				['id' => $doc_id ]);
			
			if ($row = $req->current()) {
				$path = $row["filepath"];
				$filename = $row["filename"];
			}
			
			$fullpath = GLPI_ROOT."/files/".$path;
			
			$req2 = $DB->request(
					'glpi_useremails',
					['users_id' => $id, 'is_default' => 1]);
					
			if ($row2 = $req2->current()) {
				$owner_email = $row2["email"];
			}
			
			if ($send_user == 1) {
				$nmail->AddAddress($owner_email);
			}
			
			foreach($recipients_array as $recipient) {
				
				$nmail->AddAddress($recipient); //do konfiguracji
			}
			
			$nmail->Subject = $email_subject; //do konfiguracji
			$nmail->addAttachment($fullpath, $filename);
			$nmail->Body = $email_content;
			
			if (!$nmail->Send()) {
				Session::addMessageAfterRedirect(__('Failed to send email'), false, ERROR);
				GLPINetwork::addErrorMessageAfterRedirect();
				return false;
			} else {
				
				if ($send_user == 1) {
					Session::addMessageAfterRedirect(__('Email sent')." to ".implode(", ", $recipients_array)." ".$owner_email);
					return true;
				} else {
					Session::addMessageAfterRedirect(__('Email sent')." to ".implode(", ", $recipients_array));
					return true;
				}
			}
			
			
		}
		
		static function sendOneMail($id) {
			
			global $CFG_GLPI, $DB;
			
			$nmail = new GLPIMailer();
			
			$nmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
			
			$doc_id = $_POST["doc_id"];
			
			//if email is filled manually
			if (isset($_POST["em_list"])) {
				$recipients = $_POST["em_list"];
			}
			
			if (isset($_POST["email_subject"])) {
				$email_subject = $_POST["email_subject"];
			} else {
				$email_subject = "GLPI Protocols Manager mail";
			}
			
			if (isset($_POST['email_content'])) {
				$email_content = $_POST['email_content'];
			} else {
				$email_content = ' ';
			}
			
			//if email is from template
			if (isset($_POST['e_list'])) {
				$result = explode('|', $_POST['e_list']);
				$recipients = $result[0];
				$email_subject = $result[1];
				$email_content =  $result[2];
				$send_user =  $result[3];
			}
			
			$owner = $_POST["owner"];
			$author = $_POST["author"];
			
			$email_content = str_replace("{owner}", $owner, $email_content);
			$email_content = str_replace("{admin}", $author, $email_content);
			$email_content = str_replace("{cur_date}", date("d.m.Y"), $email_content);
			
			$email_subject = str_replace("{owner}", $owner, $email_subject);
			$email_subject = str_replace("{admin}", $author, $email_subject);
			$email_subject = str_replace("{cur_date}", date("d.m.Y"), $email_subject);
			
			$recipients_array = explode(';',$recipients);
			
			$req2 = $DB->request(
					'glpi_useremails',
					['users_id' => $id, 'is_default' => 1]);
					
			if ($row2 = $req2->current()) {
				$owner_email = $row2["email"];
			}
			
			if ($send_user == 1) {
				$nmail->AddAddress($owner_email);
			}
			
			foreach($recipients_array as $recipient) {
				
				$nmail->AddAddress($recipient); //do konfiguracji
			}
			
			$req = $DB->request(
					'glpi_documents',
					['id' => $doc_id ]);
			
			if ($row = $req->current()) {
				$path = $row["filepath"];
				$filename = $row["filename"];
			}
			
			$fullpath = GLPI_ROOT."/files/".$path;
			
			$nmail->IsHtml(true);
			
			$nmail->Subject = $email_subject; //do konfiguracji
			$nmail->addAttachment($fullpath, $filename);
			$nmail->Body = nl2br(stripcslashes($email_content));
			
			if (!$nmail->Send()) {
				Session::addMessageAfterRedirect(__('Failed to send email'), false, ERROR);
				return false;
			} else {
				
				if ($send_user == 1) {
					Session::addMessageAfterRedirect(__('Email sent')." to ".implode(", ", $recipients_array)." ".$owner_email);
					return true;
				} else {
					Session::addMessageAfterRedirect(__('Email sent')." to ".implode(", ", $recipients_array));
					return true;
				}
			}
			
		}
		
	
}


?>

<script>

	$(function(){
		$(".man_recs").prop('disabled', true);
		$('.send_type').click(function(){
			if($(this).prop('id') == "manually"){
				$(".man_recs").prop('disabled', false);
				$("#auto_recs").prop('disabled', true);
			}else{
				$(".man_recs").prop('disabled', true);
				$("#auto_recs").prop('disabled', false);
			}
		});
	});

	// ça utilisait jqueryUI, glpi ne l'utilise plus cependant

	$(function(){
		
		$("#myTable").on('click','.openDialog',function(){

			// get the current row
			var currentRow = $(this).closest("tr");

			// get current row 1st table cell TD value
			var docid = currentRow.find(".docid").html(); 
			
			$('#dialogVal').val(docid);

			// on affiche la pop-up
			$("#motus").modal('show');
			
		});
			
	});

	// OK à retoucher pour que ça soit une option dans le plugin ?
	$(function(){

		// Par défaut on coche les cases
		$('.checkall').prop('checked', true);
		$('.child').prop('checked', this)

		// Quand on clique sur le parent ça change toutes les cases
		$('.checkall').on('click', function() {
		$('.child').prop('checked', this.checked)
		});
	});

	$(function(){
		$('.checkalldoc').on('click', function() {
			$('.docchild').prop('checked', this.checked)
		});
	});

	// To do : store in db
	// a CHANGER jqueryui
	$(function() {

			var counter = $('.child').length;
			
			var ctr = 0;
			
			$("#addNewRow").on("click", function () {
				var newRow = $("<tr class='tab_bg_1'>");
			var cols = "";
			
			cols += '<td><input type="button" class="ibtnDel" value="&#10006" style="background-color:red; font-size:9px;"></td>';
			cols += '<td class="center"><input type="text" style="width:80% " name="type_name[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="man_name[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="mod_name[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="item_name[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="serial[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="otherserial[]"></td>';
			cols += '<td class="center"><input type="text" style="width:90% "name="comments[]"><input type="hidden" name="number[]" value="' + counter + '"></td>';
			
			newRow.append(cols);
			$("#additional_table").append(newRow);
			counter++;
			ctr++;
		});
		
		$("#additional_table").on("click", ".ibtnDel", function (event) {
			$(this).closest("tr").remove();
			ctr -= 1
		});


	});

</script>
