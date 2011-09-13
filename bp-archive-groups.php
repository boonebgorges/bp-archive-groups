<?php

class BP_Archive_Groups extends BP_Component {
	var $archive_status;
	var $status_whitelist;

	function __construct() {
		parent::start(
			'archive_groups',
			__( 'BP Archive Groups', 'buddypress' ),
			BPAG_INSTALL_DIR
		);

		$this->setup_status();

		// Don't filter on directories
		if ( !bp_is_directory() ) {
			add_filter( 'bp_groups_get_paged_groups_sql', array( $this, 'filter_sql' ) );
			add_filter( 'bp_groups_get_total_groups_sql', array( $this, 'filter_sql' ) );
		}
	}

	function setup_status() {
		$this->status_whitelist = apply_filters( 'bpag_status_whitelist', array(
			'archived',
			'unarchived',
			'all'
		) );

		// Get the filter status out of the $_GET global. If none is provided, default
		// to 'unarchived'
		if ( isset( $_GET['bpag_status'] ) && in_array( $_GET['bpag_status'], $this->status_whitelist ) ) {
			$this->archive_status = $_GET['bpag_status'];
		} else {
			$this->archive_status = 'unarchived';
		}
	}

	function filter_sql( $sql ) {
		global $bp, $wpdb;

		if ( 'archived' == $this->archive_status ) {
			// Get a list of archived groups. Todo: where not exists for non-archived
			if ( !$include_groups = wp_cache_get( 'bpag_' . $this->archive_status . '_groups' ) ) {
				$include_groups = $wpdb->get_col( $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'bpag_status' AND meta_value = %s", $this->archive_status ) );

				wp_cache_set( 'bpag_' . $this->archive_status . '_groups', $include_groups );
			}

			// Modify the SQL as necessary
			if ( !empty( $include_groups ) ) {
				$in_sql = " g.id IN (" . implode( ',', $include_groups ) . ") AND ";
				$sql_a = explode( 'WHERE', $sql );
				$sql = $sql_a[0] . 'WHERE' . $in_sql . $sql_a[1];
			}
		} else if ( 'unarchived' == $this->archive_status ) {
			// We have to do a NOT EXISTS subquery, which means we can't use WP cache
			$sql_a = explode( 'WHERE', $sql );
			$subquery = $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} gm_ag WHERE g.id = gm_ag.group_id AND gm_ag.meta_key = 'bpag_status'" );
			$sql = $sql_a[0] . 'WHERE NOT EXISTS ( ' . $subquery . ' ) AND ' . $sql_a[1];
		}

		return $sql;
	}
}

global $bp;
$bp->archive_groups = new BP_Archive_Groups;

?>