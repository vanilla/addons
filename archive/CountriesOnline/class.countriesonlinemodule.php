<?php if (!defined('APPLICATION')) exit();

/**
* Renders a count of users per country in the side panel. 
*/
class CountriesOnlineModule extends Gdn_Module 
{
	protected $geoip_installed = false;
	protected $default_country_code = 'VF';
	protected $db_data;
	protected $grouped_data;
	protected $user_country;
	protected $time_now;
	protected $time_threshold;

	public function __construct() 
	{
		// If geoip values do not exist, just set default "VF," vanilla forum 
		// country code, i.e., unknown.
		$this->user_country = Gdn::Request()->GetValueFrom(Gdn_Request::INPUT_SERVER, 'GEOIP_COUNTRY_CODE', $this->default_country_code);
		
		// If country code was set to VF, it's most likely because the GeoIP module 
		// is not installed, or it's running on a local machine, so let user know.
		if ($this->user_country !== $this->default_country_code)
		{
			$this->geoip_installed = true;
		}
		
		// This option was commented out in Gdn_Format (ToTimestamp, line 1150), 
		// so just use function call 
		$this->time_now = time();
	}

	public function GetData($time_threshold = false) 
	{
		$SQL = Gdn::SQL();
		$Session = Gdn::Session();

		// insert or update into table
		if ($Session->UserID) 
		{
			$SQL->Replace(
				'CountriesOnline', 
				array(
					'UserID' => $Session->UserID,
					'CountryCode' => $this->user_country,
					'Timestamp' => $this->time_now
				), 
				array(
					'UserID' => $Session->UserID
				)
			);
		 
			// How many minutes back to display country stats? Default is 5
			$this->time_threshold = (!$time_threshold) 
				? C('Plugin.CountriesOnline.TimeThreshold') 
				: $time_threshold;
			
			$threshold_candidates = $this->time_now - $this->time_threshold;

			// Grab relevant data, then pass to grouping method
			$SQL
				->Select('CountryCode, Timestamp')
				->From('CountriesOnline')
				->Where('Timestamp >=', $threshold_candidates);

			$this->db_data = $SQL->Get();
			$this->grouped_data = $this->groupCountryData();
		}
	}

	public function AssetTarget() 
	{
		return 'Panel';
	}

	/**
	 * The markup for the panel box.
	 */
	public function ToString() 
	{
		$string = '';
		$countries_online = count($this->grouped_data);
		
		if ($countries_online > 0) 
		{
			ob_start();
			?>
				<div id="CountriesOnline" class="Box">
					<h4 title="Online users per country from the last <?php echo floor($this->time_threshold / 60); ?> minutes">
						Country's Online (<?php echo $countries_online; ?>)
					</h4>
					<ul class="PanelInfo">
						
						<?php foreach($this->grouped_data as $country_code => $user_count) { ?>
							
							<li>
								<strong class="cc_<?php echo $country_code; ?>">
									<img src="/plugins/countriesonline/design/flags/<?php echo strtolower($country_code); ?>.png" alt="" /> 
									<?php echo $this->getNameFromCountryCode($country_code); ?>
								</strong>
								<?php echo $user_count; ?>
							</li>
							
						<?php	} ?>
						
					</ul>
					
					<?php if (!$this->geoip_installed) { ?>
						
						<p class="geoip_notice">
							If you see this message, it's either because you do not have the 
							<a href="http://google.com/search?q=geoip+module">GeoIP module</a> 
							installed, or you're running it locally.
						</p>
					
					<?php } ?>
				
				</div>
			
			<?php
			$string = ob_get_clean();
		}
		
		return $string;
	}
	
	/**
	 * Manipulate the db_data to group it how you'd like. Avoided doing expensive 
	 * queries, and gives more flexibility. Besides, the odds of the language 
	 * changing before the DB are minimal.
	 */
	private function groupCountryData()
	{
		$grouped_countries = array();
		
		if ($this->db_data->NumRows() > 0)
		{
			foreach($this->db_data->Result() as $row)
			{
				$grouped_countries[$row->CountryCode]++;
			}
			
			// sort by count per group, then alphabetical for any duplicate counts
			array_multisort(array_values($grouped_countries), SORT_DESC, array_keys($grouped_countries), SORT_ASC, $grouped_countries);
		}
		
		return $grouped_countries;
	}
	
	/**
	 * Reason to have this here is for T() function to make localization easier 
	 * in the future. Country codes will always be inserted as two latin chars.
	 */
	private function getNameFromCountryCode($country_code)
	{
		$countries = array(
			$this->default_country_code => "Unknown", // Define whatever name you want for unknown code
			"AU" => "Australia",
			"AF" => "Afghanistan",
			"AL" => "Albania",
			"DZ" => "Algeria",
			"AS" => "American Samoa",
			"AD" => "Andorra",
			"AO" => "Angola",
			"AI" => "Anguilla",
			"AQ" => "Antarctica",
			"AG" => "Antigua & Barbuda",
			"AR" => "Argentina",
			"AM" => "Armenia",
			"AW" => "Aruba",
			"AT" => "Austria",
			"AZ" => "Azerbaijan",
			"BS" => "Bahamas",
			"BH" => "Bahrain",
			"BD" => "Bangladesh",
			"BB" => "Barbados",
			"BY" => "Belarus",
			"BE" => "Belgium",
			"BZ" => "Belize",
			"BJ" => "Benin",
			"BM" => "Bermuda",
			"BT" => "Bhutan",
			"BO" => "Bolivia",
			"BA" => "Bosnia/Hercegovina",
			"BW" => "Botswana",
			"BV" => "Bouvet Island",
			"BR" => "Brazil",
			"IO" => "British Indian Ocean Territory",
			"BN" => "Brunei Darussalam",
			"BG" => "Bulgaria",
			"BF" => "Burkina Faso",
			"BI" => "Burundi",
			"KH" => "Cambodia",
			"CM" => "Cameroon",
			"CA" => "Canada",
			"CV" => "Cape Verde",
			"KY" => "Cayman Is",
			"CF" => "Central African Republic",
			"TD" => "Chad",
			"CL" => "Chile",
			"CN" => "China, People's Republic of",
			"CX" => "Christmas Island",
			"CC" => "Cocos Islands",
			"CO" => "Colombia",
			"KM" => "Comoros",
			"CG" => "Congo",
			"CD" => "Congo, Democratic Republic",
			"CK" => "Cook Islands",
			"CR" => "Costa Rica",
			"CI" => "Cote d'Ivoire",
			"HR" => "Croatia",
			"CU" => "Cuba",
			"CY" => "Cyprus",
			"CZ" => "Czech Republic",
			"DK" => "Denmark",
			"DJ" => "Djibouti",
			"DM" => "Dominica",
			"DO" => "Dominican Republic",
			"TP" => "East Timor",
			"EC" => "Ecuador",
			"EG" => "Egypt",
			"SV" => "El Salvador",
			"GQ" => "Equatorial Guinea",
			"ER" => "Eritrea",
			"EE" => "Estonia",
			"ET" => "Ethiopia",
			"FK" => "Falkland Islands",
			"FO" => "Faroe Islands",
			"FJ" => "Fiji",
			"FI" => "Finland",
			"FR" => "France",
			"FX" => "France, Metropolitan",
			"GF" => "French Guiana",
			"PF" => "French Polynesia",
			"TF" => "French South Territories",
			"GA" => "Gabon",
			"GM" => "Gambia",
			"GE" => "Georgia",
			"DE" => "Germany",
			"GH" => "Ghana",
			"GI" => "Gibraltar",
			"GR" => "Greece",
			"GL" => "Greenland",
			"GD" => "Grenada",
			"GP" => "Guadeloupe",
			"GU" => "Guam",
			"GT" => "Guatemala",
			"GN" => "Guinea",
			"GW" => "Guinea-Bissau",
			"GY" => "Guyana",
			"HT" => "Haiti",
			"HM" => "Heard Island And Mcdonald Island",
			"HN" => "Honduras",
			"HK" => "Hong Kong",
			"HU" => "Hungary",
			"IS" => "Iceland",
			"IN" => "India",
			"ID" => "Indonesia",
			"IR" => "Iran",
			"IQ" => "Iraq",
			"IE" => "Ireland",
			"IL" => "Israel",
			"IT" => "Italy",
			"JM" => "Jamaica",
			"JP" => "Japan",
			"JT" => "Johnston Island",
			"JO" => "Jordan",
			"KZ" => "Kazakhstan",
			"KE" => "Kenya",
			"KI" => "Kiribati",
			"KP" => "Korea, Democratic Peoples Republic",
			"KR" => "Korea, Republic of",
			"KW" => "Kuwait",
			"KG" => "Kyrgyzstan",
			"LA" => "Lao People's Democratic Republic",
			"LV" => "Latvia",
			"LB" => "Lebanon",
			"LS" => "Lesotho",
			"LR" => "Liberia",
			"LY" => "Libyan Arab Jamahiriya",
			"LI" => "Liechtenstein",
			"LT" => "Lithuania",
			"LU" => "Luxembourg",
			"MO" => "Macau",
			"MK" => "Macedonia",
			"MG" => "Madagascar",
			"MW" => "Malawi",
			"MY" => "Malaysia",
			"MV" => "Maldives",
			"ML" => "Mali",
			"MT" => "Malta",
			"MH" => "Marshall Islands",
			"MQ" => "Martinique",
			"MR" => "Mauritania",
			"MU" => "Mauritius",
			"YT" => "Mayotte",
			"MX" => "Mexico",
			"FM" => "Micronesia",
			"MD" => "Moldavia",
			"MC" => "Monaco",
			"MN" => "Mongolia",
			"MS" => "Montserrat",
			"MA" => "Morocco",
			"MZ" => "Mozambique",
			"MM" => "Union Of Myanmar",
			"NA" => "Namibia",
			"NR" => "Nauru Island",
			"NP" => "Nepal",
			"NL" => "Netherlands",
			"AN" => "Netherlands Antilles",
			"NC" => "New Caledonia",
			"NZ" => "New Zealand",
			"NI" => "Nicaragua",
			"NE" => "Niger",
			"NG" => "Nigeria",
			"NU" => "Niue",
			"NF" => "Norfolk Island",
			"MP" => "Mariana Islands, Northern",
			"NO" => "Norway",
			"OM" => "Oman",
			"PK" => "Pakistan",
			"PW" => "Palau Islands",
			"PS" => "Palestine",
			"PA" => "Panama",
			"PG" => "Papua New Guinea",
			"PY" => "Paraguay",
			"PE" => "Peru",
			"PH" => "Philippines",
			"PN" => "Pitcairn",
			"PL" => "Poland",
			"PT" => "Portugal",
			"PR" => "Puerto Rico",
			"QA" => "Qatar",
			"RE" => "Reunion Island",
			"RO" => "Romania",
			"RU" => "Russian Federation",
			"RW" => "Rwanda",
			"WS" => "Samoa",
			"SH" => "St Helena",
			"KN" => "St Kitts & Nevis",
			"LC" => "St Lucia",
			"PM" => "St Pierre & Miquelon",
			"VC" => "St Vincent",
			"SM" => "San Marino",
			"ST" => "Sao Tome & Principe",
			"SA" => "Saudi Arabia",
			"SN" => "Senegal",
			"SC" => "Seychelles",
			"SL" => "Sierra Leone",
			"SG" => "Singapore",
			"SK" => "Slovakia",
			"SI" => "Slovenia",
			"SB" => "Solomon Islands",
			"SO" => "Somalia",
			"ZA" => "South Africa",
			"GS" => "South Georgia and South Sandwich",
			"ES" => "Spain",
			"LK" => "Sri Lanka",
			"XX" => "Stateless Persons",
			"SD" => "Sudan",
			"SR" => "Suriname",
			"SJ" => "Svalbard and Jan Mayen",
			"SZ" => "Swaziland",
			"SE" => "Sweden",
			"CH" => "Switzerland",
			"SY" => "Syrian Arab Republic",
			"TW" => "Taiwan, Republic of China",
			"TJ" => "Tajikistan",
			"TZ" => "Tanzania",
			"TH" => "Thailand",
			"TL" => "Timor Leste",
			"TG" => "Togo",
			"TK" => "Tokelau",
			"TO" => "Tonga",
			"TT" => "Trinidad & Tobago",
			"TN" => "Tunisia",
			"TR" => "Turkey",
			"TM" => "Turkmenistan",
			"TC" => "Turks And Caicos Islands",
			"TV" => "Tuvalu",
			"UG" => "Uganda",
			"UA" => "Ukraine",
			"AE" => "United Arab Emirates",
			"GB" => "United Kingdom",
			"UM" => "US Minor Outlying Islands",
			"US" => "USA",
			"HV" => "Upper Volta",
			"UY" => "Uruguay",
			"UZ" => "Uzbekistan",
			"VU" => "Vanuatu",
			"VA" => "Vatican City State",
			"VE" => "Venezuela",
			"VN" => "Vietnam",
			"VG" => "Virgin Islands (British)",
			"VI" => "Virgin Islands (US)",
			"WF" => "Wallis And Futuna Islands",
			"EH" => "Western Sahara",
			"YE" => "Yemen Arab Rep.",
			"YD" => "Yemen Democratic",
			"YU" => "Yugoslavia",
			"ZR" => "Zaire",
			"ZM" => "Zambia",
			"ZW" => "Zimbabwe"
		);
		
		return $countries[$country_code];
	}
}