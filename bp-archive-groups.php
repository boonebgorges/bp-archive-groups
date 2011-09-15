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

		add_filter( 'bp_groups_get_paged_groups_sql', array( $this, 'filter_sql' ) );
		add_filter( 'bp_groups_get_total_groups_sql', array( $this, 'filter_sql' ) );
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

	/**
	 * Filters the BP_Groups_Group::get() SQL queries
	 *
	 * There's no great way to do this in BP. I've chosen to parse the SQL query string, so that
	 * I maintain maximum compatilibity with other plugins that might manipulate the query
	 */
	function filter_sql( $sql ) {
		global $bp, $wpdb;

		// Only apply this filter on user pages
		if ( !bp_is_user() ) {
			return $sql;
		}

		if ( 'all' == $this->archive_status ) {
			return $sql;
		}

		// Sniffing the $user_id out of the sql query, ugh
		preg_match( '|user_id\s?=\s?([0-9]+)\s|', $sql, $matches );
		if ( isset( $matches[1] ) ) {
			$user_id = $matches[1];
			$type    = 'user';
		} else {
			$user_id = false;
			$type	 = 'sitewide';
		}

		switch ( $type ) {
			case 'user' :
				$include_groups = get_user_meta( $user_id, 'bp_archived_groups', true );

				// Modify the SQL as necessary
				if ( !empty( $include_groups ) ) {
					$op = 'archived' == $this->archive_status ? 'IN' : 'NOT IN';

					$in_sql = " g.id {$op} (" . implode( ',', $include_groups ) . ") AND ";
					$sql_a = explode( 'WHERE', $sql );
					$sql = $sql_a[0] . 'WHERE' . $in_sql . $sql_a[1];
				}

				break;

			case 'sitewide' :
				if ( 'archived' == $this->archive_status ) {
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
				} else {
					// We have to do a NOT EXISTS subquery, which means we can't use WP cache
					$sql_a = explode( 'WHERE', $sql );
					$subquery = $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} gm_ag WHERE g.id = gm_ag.group_id AND gm_ag.meta_key = 'bpag_status'" );
					$sql = $sql_a[0] . 'WHERE NOT EXISTS ( ' . $subquery . ' ) AND ' . $sql_a[1];
				}
				break;
		}

		return $sql;
	}

	/**
	 * Archive a group
	 *
	 * If a user_id is provided, it's archived only for that user
	 */
	function archive_group( $group_id = false, $user_id = false ) {
		if ( !$group_id ) {
			$group_id = bp_get_current_group_id();
		}

		if ( !$group_id ) {
			$return = -1;
		}

		if ( $user_id ) {
			// Individual user archived groups are stored in a usermeta value
			if ( !$user_archived_groups = get_user_meta( $user_id, 'bp_archived_groups', true ) ) {
				$user_archived_groups = array();
			}

			if ( in_array( $group_id, $user_archived_groups ) ) {
				// Already archived
				$return = -2;
			} else {
				$user_archived_groups[] = $group_id;
				if ( update_user_meta( $user_id, 'bp_archived_groups', $user_archived_groups ) ) {
					$return = 1;
				} else {
					$return = -3;
				}
			}

		} else {
			// Groups that are archived sitewide are archived in groupmeta
			if ( 'archived' == groups_get_groupmeta( $group_id, 'bpag_status' ) ) {
				$return = -2;
			} else {
				if ( groups_update_groupmeta( $group_id, 'bpag_status', 'archived' ) ) {
					$return = 1;
				} else {
					$return = -3;
				}
			}
		}

		return $return;
	}
}

global $bp;
$bp->archive_groups = new BP_Archive_Groups;

?>