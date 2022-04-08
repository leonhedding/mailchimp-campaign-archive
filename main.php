<?PHP
/*
Plugin Name: MailChimp Campaign Archive
Plugin URI: http://JoeAnzalone.com/plugins/mailchimp-campaign-archive/
Description: Adds a [mailchimp_campaigns] shortcode that lists your latest MailChimp email campaigns
Version: 1.0.3
Author: Joe Anzalone
Author URI: http://JoeAnzalone.com
License: GPL2
*/

class mailchimp_campaign_archive {

	function mailchimp_campaigns_shortcode($params){
		$html = '';
		$default_params = array(
			'apikey' => null,
			'limit' => 10,
			'offset' => 0,
			'order' => 'DESC',
			'link_text' => 'subject',
			'status' => 'sent',
			'from_name' => null,
			'from_email' => null,
			'list_id' => null,
			'folder_id' => null,
			'sendtime_start' => null,
			'organize_by' => null,
			'heading_tag' => 'h2',
		);

		// Remove any HTML that might've accidentally polluted the API key (such as <wbr>)
		$params['apikey'] = wp_filter_nohtml_kses( $params['apikey'] );

		foreach($default_params as $k => $v){
			if(!isset($params[$k])){
				$params[$k] = $v;
			}
		}

		// Require MailChimp's official API wrapper
		require_once 'src/Mailchimp.php';

		$api = new Mailchimp( $params['apikey'] );

		$filters = array(
			'status' => $params['status'],
			'from_name' =>  $params['from_name'],
			'from_email' => $params['from_email'],
			'list_id' => $params['list_id'],
			'folder_id' => $params['folder_id'],
			'sendtime_start' => $params['sendtime_start'],
		);

		// http://apidocs.mailchimp.com/api/1.3/campaigns.func.php
		$retval = $api->campaigns->getList( $filters, 0, 1000 );

		if (isset($api->errorCode)){
			// Output an HTML comment if an error occurs
			$html .= '<!-- ';
			$html .= "Unable to retrieve list of campaigns";
			$html .= "\n\tCode=".$api->errorCode;
			$html .= "\n\tMsg=".$api->errorMessage."\n";
			$html .= ' -->';
			return $html;
		} else {
			$campaigns = $retval['data'];

			if ( strcasecmp( $params['order'], 'DESC' ) ) {
				$campaigns = array_reverse( $campaigns );
			}

			// If $params['offset'] is non-negative, the sequence will start at that offset in the array. If offset is negative, the sequence will start that far from the end of the array.
			// Limit the number of results to the value used in the shortcode's "limit" parameter
			$campaigns = array_slice($campaigns, $params['offset'], $params['limit'] );

			if ( $params['organize_by'] ) {

				foreach ($campaigns as $c) {
					if ( $c['send_time'] ) {
						// Use the sent time if there is one
						$timestamp = strtotime( $c['send_time'] );
					} else {
						// Otherwise use the creation time (Used for drafts that haven't been sent)
						$timestamp = strtotime( $c['create_time'] );
					}

					if ( $params['organize_by'] == 'month' ) {
						// Set section title to month
						$section_title = date('F Y', $timestamp);
					} elseif ( $params['organize_by'] == 'year' ) {
						// Set section title to year
						$section_title = date('Y', $timestamp);
					}
					// Organize campain based on section title
	    			$campaigns_organized[ $section_title ][] = $c;
				}
				// Save the newly sorted campaigns back into the original $campaigns array
				$campaigns = $campaigns_organized;
			} else {
				// Wrap unorganized campaigns in an array for consistency
				// so that "foreach ( $campaigns as $section_title => $campaigns )" works
				$campaigns = array( $campaigns );
			}

			foreach ( $campaigns as $section_title => $campaigns ) {
				if ( $section_title ) {
					// Put a heading above each UL such as <h2>August 2012</h2>
					$html .= '<' . $params['heading_tag'] . ' class="'. $params['organize_by'] .'">' . $section_title . '</' . $params['heading_tag'] . '>';
				}

				// Begin the list
			    $html .= '<ul class="mailchimp-campaigns" class="'. $params['organize_by'] .'">';
			    foreach($campaigns as $c){
			    // Iterate over each email campaign in the list

			    	$link_text = esc_html( $c[$params['link_text']] );
			        $html .= '<li class="' . $c['status'] . '">';
			        $html .= '<a rel="external" target="_blank" href="'. $c['archive_url'] .'">' . $link_text . '</a>';
			        $html .= '</li>';

			    }

			    // End the list
		    	$html .= '</ul>';
			}
	}

		// Return the shortcode's output
		return $html;
	}

	function __construct(){
		// Register the [mailchimp_campaigns] shortcode
		add_shortcode( 'mailchimp_campaigns', array($this,'mailchimp_campaigns_shortcode') );
	}

}

// Instantiate a mailchimp_campaign_archive object and run the constructor
$mailchimp_campaign_archive = new mailchimp_campaign_archive;
