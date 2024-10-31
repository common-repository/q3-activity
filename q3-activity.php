<?php
/*
Plugin Name: q3-activity
Plugin URI: https://github.com/ytppa
Description: Monitors user activity. Shows a list of users and the number of posts, created for a specified period of time.
Armstrong: My Plugin.
Author: ytppa
Version: 0.3
Author URI: https://github.com/ytppa
*/



// Creating a menu page in admin panel.
function q3_add_admin_pages() 
{
    // Add a new submenu under Options:
    add_menu_page('Отчет об активности пользователей', 'Отчеты', 5, 'q3-activity', 'q3_activity_page', 'dashicons-analytics');
}



//
if ( ! defined( 'WP_CONTENT_URL' ) ) {
    if ( defined( 'WP_SITEURL' ) ) {
        define( 'WP_CONTENT_URL', WP_SITEURL . '/wp-content' );
    } else {
        define( 'WP_CONTENT_URL', get_bloginfo('wpurl') . '/wp-content' );
    }
} 
define('Q3_DIR', dirname(plugin_basename(__FILE__)));
define('Q3_URL', WP_CONTENT_URL . '/plugins/' . Q3_DIR . '/');



// Adding a class to create tables in admin panel
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}



//
// Класс для вывода таблицы 1
//
class Q3_List_Table extends WP_List_Table 
{

    // Отключим аякс
    function __construct()
    {
        global $status, $page;

        parent::__construct( array(
            'singular'  => __( 'Пользователь', 'mylisttable' ),     //singular name of the listed records
            'plural'    => __( 'Пользователи', 'mylisttable' ),   //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
    }
    
    // Столбцы по умолчанию.
    function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'id':
            case 'display_name':
            case 'c':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }
    
    // Зададим столбцы
    function get_columns()
    {
        $columns = array(
            'id' => __( 'id', 'mylisttable' ),
            'display_name'    => __( 'Пользователь', 'mylisttable' ),
            'c'      => __( 'Записей создано', 'mylisttable' )
        );
        return $columns;
    }

    // Подготовим данные
    function prepare_items($rows) 
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = array(); // $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->items = $rows;
        echo ('<style type="text/css">
                .wp-list-table .column-id { width: 5%;  }
                .wp-list-table .column-display_name { width: 85%; }
                .wp-list-table .column-c { width: 10%; }
                </style>'
        );
    }
}



//
// Основной класс отчетов
//
class Q3_Activity_Report
{
    // Предустановка переменных выборки
    var $user_id = ''
        ,$q3_date_start = ''
        ,$q3_date_end = ''
        ,$query_add = '';
   
    function Q3_Activity_Report() 
    {
        $this->q3_date_start = date('Y/m/d',time()-604800);
        $this->q3_date_end = date('Y/m/d');
        $this->query_add = "";

        // Получение параметров для выборки
        if ( isset($_POST['q3_search_form_btn']) ) 
        {   
           if (function_exists('current_user_can') && 
                !current_user_can('manage_options') )
                    die ( _e('Hacker?', 'q3') );

            if (function_exists ('check_admin_referer') )
            {
                check_admin_referer('q3_search_form');
            }
            
            if ( isset($_POST['q3_date_start']) && isset($_POST['q3_date_end']) ) 
            {
                $act_d_s_tab = explode('/', esc_html($_POST['q3_date_start']));
                $act_d_e_tab = explode('/', esc_html($_POST['q3_date_end'])); 
                $this->q3_date_start = $act_d_s_tab[2].'/'.$act_d_s_tab[1].'/'.$act_d_s_tab[0];
                $this->q3_date_end = $act_d_e_tab[2].'/'.$act_d_e_tab[1].'/'.$act_d_e_tab[0];
            }  
            
            $this->user_id = (strlen(trim($_POST['q3_user_id']))) ? $_POST['q3_user_id'] : '';
            $this->q3_date_start = $_POST['q3_date_start'];
            $this->q3_date_end = $_POST['q3_date_end'];
        }
        $this->query_add = " AND post_date BETWEEN '".addslashes($this->q3_date_start)."' AND '".addslashes($this->q3_date_end)." 23:59:59'";
    }



    // Соберем окончание для заголовка
    function get_title_postfix()
    {
        global $wpdb;
        $t = '';
        
        if (strlen($this->user_id))
        {
            $user_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM ".$wpdb->prefix."users WHERE ID = '".$this->user_id."';", ''));
            $t .= " пользователя ".htmlspecialchars($user_name);
        }
        else $t .= " пользователей";

        $t0 = "";
        if (strlen($this->q3_date_start))
            $t0 .= " с ".htmlspecialchars($this->q3_date_start);
        if (strlen($this->q3_date_end))
            $t0 .= " по ".htmlspecialchars($this->q3_date_end);
        if (strlen($t0))
            $t .= " в период".$t0;

        return $t.".";
    }



    // Формирование таблицы с отчетом.
    function report_table() 
    {
        global $wpdb;

        // Соберем данные для отчета
        $rows = $wpdb->get_results($wpdb->prepare("SELECT u.id, u.display_name, COUNT(p.id) as c 
            FROM ".$wpdb->prefix."users as u, ".$wpdb->prefix."posts as p
            WHERE p.post_author = u.id
                AND p.post_status = 'publish'
                AND post_parent = '0'
                AND post_type = 'post'"
            . ( (strlen($this->user_id)) ? " AND u.id = '".$this->user_id."'" : "" )
            . $this->query_add
            . " GROUP BY p.post_author ORDER BY c DESC;", ''));
        
        // Подготовим массив данных для таблицы
        $items_arr = array();
        foreach ($rows as $item) 
        {
            array_push($items_arr, array("id"=>$item->id, "display_name"=>$item->display_name, "c"=>$this->link($item)));
        }

        // Сформируем и отдадим таблицу
        $list_tabe = new Q3_List_Table();
        $list_tabe->prepare_items($items_arr); 
        $list_tabe->display(); 
    }

    function link($item)
    {
        $t = '<a href="/wp-admin/edit.php?post_status=publish&post_type=post&author=' 
            . $item->id 
            . '&q3_after=' . $this->q3_date_start
            . '&q3_before=' . $this->q3_date_end
            . '">' . $item->c . '</a>';
            /*. '&q3_after=' . str_replace("/", "-", $this->q3_date_start) 
            . '&q3_before=' . str_replace("/", "-", $this->q3_date_end) */
        return $t;
    }
    

    // Параметры выборки для отчета
    function search_form()
    {
        global $wpdb,$options_act;
        
        // Подключим скрипт датапикера
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('q3_datepicker', Q3_URL .'jquery.ui.datepicker.css', false, '2.5.0', 'screen');

        // Форма добавления товара
        echo
            "
                <form name='q3_add_product' method='POST' action='".$_SERVER['PHP_SELF']."?page=q3-activity&amp;updated=true'>
            ";
            
            if (function_exists ('wp_nonce_field') )
            {
                wp_nonce_field('q3_search_form'); 
            }
        
        // предопределим формат полей дат.
        echo '<script>
            jQuery().ready(function($){
              $.datepicker.regional["Q3"] = {
                closeText: "'.__('Close', 'q3-activity').'",
                prevText: "'.__('&#x3c;Prev', 'q3-activity').'",
                nextText: "'.__('Next&#x3e;', 'q3-activity').'",
                currentText: "'.__('Current', 'q3-activity').'",
                monthNames: ["'.__('January').'","'.__('February').'","'.__('March').'","'.__('April').'","'.__('May').'","'.__('June').'",
                "'.__('July').'","'.__('August').'","'.__('September').'","'.__('October').'","'.__('November').'","'.__('December').'"],
                monthNamesShort: ["'.__('Jan_January_abbreviation').'","'.__('Feb_February_abbreviation').'","'.__('Mar_March_abbreviation').'","'.__('Apr_April_abbreviation').'","'.__('May_May_abbreviation').'","'.__('Jun_June_abbreviation').'",
                "'.__('Jul_July_abbreviation').'","'.__('Aug_August_abbreviation').'","'.__('Sep_September_abbreviation').'","'.__('Oct_October_abbreviation').'","'.__('Nov_November_abbreviation').'","'.__('Dec_December_abbreviation').'"],
                dayNames: ["'.__('Sunday').'","'.__('Monday').'","'.__('Tuesday').'","'.__('Wednesday').'","'.__('Thursday').'","'.__('Friday').'","'.__('Saturday').'"],
                dayNamesShort: ["'.__('Sun').'","'.__('Mon').'","'.__('Tue').'","'.__('Wed').'","'.__('Thu').'","'.__('Fri').'","'.__('Sat').'"],
                dayNamesMin: ["'.__('S_Sunday_initial').'","'.__('M_Monday_initial').'","'.__('T_Tuesday_initial').'","'.__('W_Wednesday_initial').'","'.__('T_Thursday_initial').'","'.__('F_Friday_initial').'","'.__('S_Saturday_initial').'"],
                firstDay: '.get_option('start_of_week').',
                showMonthAfterYear: false,
                yearSuffix: ""};
              $.datepicker.setDefaults($.datepicker.regional["Q3"]);
            });
            </script>'; 
        // Поля для указания дат
        echo 
            '<script>
            jQuery().ready(function ($) {
              var dates = $( "#q3_date_start, #q3_date_end" ).datepicker({
                dateFormat: "yy/mm/dd",
                changeMonth: true,
                changeYear: true,
                    numberOfMonths: 1,
                    onSelect: function( selectedDate ) {
                        var option = this.id == "q3_date_start" ? "minDate" : "maxDate",
                            instance = $( this ).data( "datepicker" ),
                            date = $.datepicker.parseDate(
                                instance.settings.dateFormat ||
                                $.datepicker._defaults.dateFormat,
                                selectedDate, instance.settings );
                        dates.not( this ).datepicker( "option", option, date );
                    }
                });
            });
            </script>';

        echo
            "
                <p>
                   Диапазон дат: 
                   <input type='text' id='q3_date_start' name='q3_date_start' value='".nicetime($this->q3_date_start, true, true)."' />
                   <input type='text' id='q3_date_end' name='q3_date_end' value='".nicetime($this->q3_date_end, true, true)."' /> 
                   <input type='submit' name='q3_search_form_btn' value='Показать' style='width:140px;' class='button-secondary'/>
                </p>
            </form>
        ";
    }
}



//
// Соберем страницу
//
function q3_activity_page() 
{
    echo "<h2>Отчеты</h2>";
    
    // Создание экземпляра отчета
    $q3_report = new Q3_Activity_Report();

    $postfix = $q3_report->get_title_postfix();
    echo "<h3>Отчет об активности" . $postfix . "</h3>";
    
    // Форма с параметрами выборки
    $q3_report->search_form(); 

    // Таблица отчета
    $q3_report->report_table();   
}



//
// Запуск плагина
//
function q3_run($content) 
{
    /*
    $status_url = get_option('q3_status_url');
    preg_match('/^http(s)?\:\/\/[^\/]+\/(.*)$/i', $status_url, $matches);
    
    $real_url = $_SERVER['REQUEST_URI'];    
    preg_match('/^\/([^\?]*)(\?.+)?$/i', $real_url, $real_matches);
    
    if($real_matches[1] == $matches[2])
    {   
        if ( isset($_POST['dcode']) ) 
        {
            
        }
        else
        {
            
        }
    }*/
}



//
// функция формирования лицеприятной даты
//
function nicetime($posted_date, $admin=false, $nohour=false) 
{
    // Adapted for something found on Internet, but I forgot to keep the url... o_O
    //$act_opt=get_option('act_settings');
    $date_relative = $act_opt['act_date_relative'];
    $date_format = "yyyy/mm/dd"; // $act_opt['act_date_format'];
    $gmt_offset = get_option('gmt_offset');
    if (empty($gmt_offset) and $gmt_offset != 0){
      $timezone = get_option('timezone_string');
      $gmt = date_create($posted_date, timezone_open($timezone));
      $gmt_offset = date_offset_get($gmt) / 3600;
    }
    
    $cur_time_gmt = time();
    $in_seconds = strtotime($posted_date);
    $posted_date = gmdate("Y-m-d H:i:s", strtotime($posted_date) + ($gmt_offset*3600));
    $relative_date = '';
    $diff = $cur_time_gmt - $in_seconds;
    $months = floor($diff/2592000);
    $diff -= $months*2419200;
    $weeks = floor($diff/604800);
    $diff -= $weeks*604800;
    $days = floor($diff/86400);
    $diff -= $days*86400;
    $hours = floor($diff/3600);
    $diff -= $hours*3600;
    $minutes = floor($diff/60);
    $diff -= $minutes*60;
    $seconds = $diff;
    if ($months>0 or !$date_relative or $admin) {
        // over a month old, just show date
        if ((!$date_relative or $admin) and !$nohour) {
            $h = substr($posted_date,10);
        } else {
            $h = '';
        }
        switch ($date_format) {
        case 'dd/mm/yyyy':
            return substr($posted_date,8,2).'/'.substr($posted_date,5,2).'/'.substr($posted_date,0,4).$h;
            break;
        case 'mm/dd/yyyy':
            return substr($posted_date,5,2).'/'.substr($posted_date,8,2).'/'.substr($posted_date,0,4).$h;
            break;
        case 'yyyy/mm/dd':
        default:
            return substr($posted_date,0,4).'/'.substr($posted_date,5,2).'/'.substr($posted_date,8,2).$h;
            break;
        }
    } else {
        if ($weeks>0) {
            // weeks and days
            $relative_date .= ($relative_date?', ':'').$weeks.' '.($weeks>1? __('weeks', 'q3-activity'):__('week', 'q3-activity'));
            $relative_date .= $days>0?($relative_date?', ':'').$days.' '.($days>1? __('days', 'q3-activity'):__('day', 'q3-activity')):'';
        }
        elseif ($days>0) {
            // days and hours
            $relative_date .= ($relative_date?', ':'').$days.' '.($days>1? __('days', 'q3-activity'):__('day', 'q3-activity'));
            $relative_date .= $hours>0?($relative_date?', ':'').$hours.' '.($hours>1? __('hours', 'q3-activity'):__('hour', 'q3-activity')):'';
        }
        elseif ($hours>0) {
            // hours and minutes
            $relative_date .= ($relative_date?', ':'').$hours.' '.($hours>1? __('hours', 'q3-activity'):__('hour', 'q3-activity'));
            $relative_date .= $minutes>0?($relative_date?', ':'').$minutes.' '.($minutes>1? __('minutes', 'q3-activity'):__('minute', 'q3-activity')):'';
        }
        elseif ($minutes>0) {
            // minutes only
            $relative_date .= ($relative_date?', ':'').$minutes.' '.($minutes>1? __('minutes', 'q3-activity'):__('minute', 'q3-activity'));
        }
        else {
            // seconds only
            $relative_date .= ($relative_date?', ':'').$seconds.' '.($seconds>1? __('seconds', 'q3-activity'):__('second', 'q3-activity'));
        }
    }
    // show relative date and add proper verbiage
    return sprintf(__('%s ago', 'q3-activity'), $relative_date);
}



//=====================================================================================//

add_action('restrict_manage_posts','q3_restrict_post_date');
function q3_restrict_post_date( $screen )
{
    global $typenow;
    global $wp_query;
    if ($typenow != "post") return '';
    if (!isset($_GET["q3_after"]) OR !preg_match("/[0-9]{4}\/[0-1][0-9]\/[0-3][0-9]/", $_GET["q3_after"])) return '';
    if (!isset($_GET["q3_before"]) OR !preg_match("/[0-9]{4}\/[0-1][0-9]\/[0-3][0-9]/", $_GET["q3_before"])) return '';
    if (!isset($_GET["author"]) OR !is_numeric($_GET["author"])) return '';
    
    // Отключим дефолтное поле даты

    echo "<style>
        .tablenav select[name=m]
        ,.tablenav select[name=cat] {
            display: none;
        }      
    </style>";

    // Добавим поля выбора Диапазона даты

    // Подключим скрипт датапикера
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('q3_datepicker', Q3_URL .'jquery.ui.datepicker.css', false, '2.5.0', 'screen');

    // Форма добавления товара
  
    // предопределим формат полей дат.
    echo '<script>
        jQuery().ready(function($){
          $.datepicker.regional["Q3"] = {
            closeText: "'.__('Close', 'q3-activity').'",
            prevText: "'.__('&#x3c;Prev', 'q3-activity').'",
            nextText: "'.__('Next&#x3e;', 'q3-activity').'",
            currentText: "'.__('Current', 'q3-activity').'",
            monthNames: ["'.__('January').'","'.__('February').'","'.__('March').'","'.__('April').'","'.__('May').'","'.__('June').'",
            "'.__('July').'","'.__('August').'","'.__('September').'","'.__('October').'","'.__('November').'","'.__('December').'"],
            monthNamesShort: ["'.__('Jan_January_abbreviation').'","'.__('Feb_February_abbreviation').'","'.__('Mar_March_abbreviation').'","'.__('Apr_April_abbreviation').'","'.__('May_May_abbreviation').'","'.__('Jun_June_abbreviation').'",
            "'.__('Jul_July_abbreviation').'","'.__('Aug_August_abbreviation').'","'.__('Sep_September_abbreviation').'","'.__('Oct_October_abbreviation').'","'.__('Nov_November_abbreviation').'","'.__('Dec_December_abbreviation').'"],
            dayNames: ["'.__('Sunday').'","'.__('Monday').'","'.__('Tuesday').'","'.__('Wednesday').'","'.__('Thursday').'","'.__('Friday').'","'.__('Saturday').'"],
            dayNamesShort: ["'.__('Sun').'","'.__('Mon').'","'.__('Tue').'","'.__('Wed').'","'.__('Thu').'","'.__('Fri').'","'.__('Sat').'"],
            dayNamesMin: ["'.__('S_Sunday_initial').'","'.__('M_Monday_initial').'","'.__('T_Tuesday_initial').'","'.__('W_Wednesday_initial').'","'.__('T_Thursday_initial').'","'.__('F_Friday_initial').'","'.__('S_Saturday_initial').'"],
            firstDay: '.get_option('start_of_week').',
            showMonthAfterYear: false,
            yearSuffix: ""};
          $.datepicker.setDefaults($.datepicker.regional["Q3"]);
        });
        </script>'; 
    // Поля для указания дат
    echo 
        '<script>
        jQuery().ready(function ($) {
          var dates = $( "#q3_after, #q3_before" ).datepicker({
            dateFormat: "yy/mm/dd",
            changeMonth: true,
            changeYear: true,
                numberOfMonths: 1,
                onSelect: function( selectedDate ) {
                    var option = this.id == "q3_after" ? "minDate" : "maxDate",
                        instance = $( this ).data( "datepicker" ),
                        date = $.datepicker.parseDate(
                            instance.settings.dateFormat ||
                            $.datepicker._defaults.dateFormat,
                            selectedDate, instance.settings );
                    dates.not( this ).datepicker( "option", option, date );
                }
            });
        });
        </script>';

    echo
        "
            
               <input type='hidden' id='q3_author' name='author' value='".$_GET["author"]."' />
               С&nbsp;<input type='text' id='q3_after' name='q3_after' style='width:120px;' value='".nicetime($_GET["q3_after"], true, true)."' />
               По&nbsp;<input type='text' id='q3_before' name='q3_before' style='width:120px;' value='".nicetime($_GET["q3_before"], true, true)."' /> 
            
    ";


    /*$wp_query->query["before"] = "2011-11-01";
    $wp_query->query["after"] = "20111101";*/
/*
    echo "<pre>"; echo "<br>";
    var_dump($wp_query->query);
    var_dump($wp_query->request);
    //var_dump($wp_query->date_query);
    echo "</pre>";*/
}


add_filter('parse_query','q3_convert_date_id_to_taxonomy_term_in_query');
function q3_convert_date_id_to_taxonomy_term_in_query($wp_query) {
    global $typenow;
    $qv = &$wp_query->query_vars;
    if ($typenow != "post") return '';
    //$qv['year'] = "2005";
    if (!isset($_GET["q3_after"]) OR !isset($_GET["q3_before"])) return '';
    if (!preg_match("/[0-9]{4}\/[0-1][0-9]\/[0-3][0-9]/", $_GET["q3_after"])) return '';
    if (!preg_match("/[0-9]{4}\/[0-1][0-9]\/[0-3][0-9]/", $_GET["q3_before"])) return '';

    $qv['date_query']["after"]  = $_GET["q3_after"];
    $qv['date_query']["before"] = $_GET["q3_before"];
}

//=====================================================================================//



add_action('admin_menu', 'q3_add_admin_pages');
add_action( 'init', 'q3_run' );

?>