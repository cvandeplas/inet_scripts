<?php
/**
 * Drupal to Blogger 
 * Script to export your drupal_database to an XML format that is importable in Blogger.
 * Copyleft Christophe Vandeplas <christophe@vandeplas.com>
 *
 * This php script does the export while keeping:
 *   posts
 *   comments
 *   tags / categories 
 *   publishing date
 * However there are a few quircks:
 *   Comments are (partially) anonymized because of a security feature of Blogger
 *   URLs are not customizable, so you will create dead links
 *   Images are not changed or imported. So manual work is still necessary
 *
 * INSTRUCTIONS
 ***************
 * To use this script first create your blog into Blogger, create a test posts 
 * and export it to XML. Then run this php script and copy paste the output towards 
 * the bottom of the XML, where your test post is located.
 * Save the file and import it again in Blogger. It usually takes some time, 
 * but in the end you get the message that everything is imported correctly.
 */
 
// change these variables
$user="db_drupal_user";
$pass="db_drupal_pass";
$db="database_name";
$db_prefix="drupal_";
// also change these variables, it should be your blogger info
$global_author="Christophe Vandeplas";
$global_author_url="http://www.blogger.com/profile/01574685218200894861";
$global_author_email="christophe@vandeplas.com";





/////////////////////////////////////////////////
// you should probably NOT change anything below
//

// We'll be outputting a xml
header('Content-type: text/xml');

// It will be called downloaded.pdf
header('Content-Disposition: attachment; filename="drupal_to_blogger_export.xml"');


$sql = "SELECT * FROM ".$db_prefix."node as n JOIN ".$db_prefix."node_revisions as nr ON n.nid=nr.nid";

mysql_connect("localhost", $user, $pass) or die(mysql_error());
mysql_select_db($db) or die(mysql_error());

// Nodes
$result_node = mysql_query($sql) or die (mysql_error());
$numrows_node=mysql_numrows($result_node);
$i_node=0;
while ($i_node < $numrows_node) {
$nid = mysql_result($result_node, $i_node, "nid");
$title= htmlspecialchars(mysql_result($result_node,$i_node,"title"));
$timestamp= date("c", mysql_result($result_node,$i_node,"created"));
$body= htmlspecialchars(mysql_result($result_node,$i_node,"body"));

$sql_cc = "SELECT * FROM ".$db_prefix."node_comment_statistics WHERE nid = $nid";
$result_cc = mysql_query($sql_cc) or die (mysql_error());
$comments = mysql_result($result_cc, 0, "comment_count");

print "
<entry>
  <id>tag:drupal,post-$nid</id>
  <published>$timestamp</published>";
$sql_cat = "SELECT * from ".$db_prefix."term_node as tn JOIN ".$db_prefix."term_data as td ON tn.tid = td.tid WHERE nid = $nid";
$result_cat = mysql_query($sql_cat) or die (mysql_error());
$numrows_cat = mysql_numrows($result_cat);
print "
  <category scheme='http://schemas.google.com/g/2005#kind' term='http://schemas.google.com/blogger/2008/kind#post'/>";
$i_cat=0;
while ($i_cat < $numrows_cat) {
  $category=strtolower(mysql_result($result_cat,$i_cat,"name"));
  print"
  <category scheme='http://www.blogger.com/atom/ns#' term='$category'/>";
  $i_cat++;
}
print "
  <title type='text'>$title</title>
  <content type='html'>$body</content>
  <author><name>$global_author</name>
    <uri>$global_author_url</uri>
    <email>$global_author_email</email>
  </author>
  <thr:total>$comments</thr:total>
</entry>";


$i_node++;
}

// Comments
$sql_c = "SELECT * FROM ".$db_prefix."comments";
$result_c = mysql_query($sql_c) or die (mysql_error());
$numrows_c=mysql_numrows($result_c);
$i_c=0;
while ($i_c < $numrows_c) {

$nid = mysql_result($result_c, $i_c, "nid");
$cid = mysql_result($result_c, $i_c, "cid");
$timestamp= date("c", mysql_result($result_c,$i_c,"timestamp"));
$title = htmlspecialchars(mysql_result($result_c, $i_c, "subject"));
$body = htmlspecialchars(mysql_result($result_c, $i_c, "comment"));
$author = mysql_result($result_c, $i_c, "name");
$email = mysql_result($result_c, $i_c, "mail");
$url = mysql_result($result_c, $i_c, "homepage");

print "<entry>
  <id>tag:drupal,comment-$cid</id>
  <published>$timestamp</published>
  <category scheme='http://schemas.google.com/g/2005#kind' term='http://schemas.google.com/blogger/2008/kind#comment'/>
  <title type='text'>$title</title>
  <content type='html'>$body [posted by: $author]</content>
  <author><name>Anonymous</name>
    <email>noreply@blogger.com</email>
  </author>
  <thr:in-reply-to ref='tag:drupal,post-$nid' type='text/html'/>
</entry>
";

$i_c++;
}

echo "\n";