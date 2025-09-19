<?php

namespace YWCE\Data\Mapper;

class UserDataMapper {

	/**
	 * Map user data to an array.
	 *
	 * @param \WP_User $user
	 * @param array $selected
	 * @param array $meta
	 *
	 * @return array
	 */
	public function map( \WP_User $user, array $selected, array $meta ): array {
		$data = [];
		foreach ( $selected as $field ) {
			$data[ $field ] = match ( $field ) {
				'ID' => $user->ID,
				'Username' => $user->user_login,
				'Email' => $user->user_email,
				'First Name' => get_user_meta( $user->ID, 'first_name', true ),
				'Last Name' => get_user_meta( $user->ID, 'last_name', true ),
				'Role' => implode( ', ', $user->roles ?? [] ),
				'Registration Date' => $user->user_registered,
				default => '',
			};
		}
		foreach ( $meta as $m ) {
			$data[ $m ] = get_user_meta( $user->ID, $m, true ) ?? '';
		}

		return $data;
	}
}
