<p><?php printf( __( 'To contact the listing author, <strong>send an email to</strong> <a class="classified_contact_email" href="mailto:%1$s%2$s">%1$s</a>', 'classifieds-wp' ), $contact->email, '?subject=' . rawurlencode( $contact->subject ) ); ?></p>

<p>
	<?php _e( 'Use webmail: ', 'classifieds-wp' ); ?>

	<a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo esc_attr( $contact->email ); ?>&su=<?php echo urlencode( $contact->subject ); ?>" target="_blank" class="classified_contact_email">Gmail</a> /

	<a href="http://webmail.aol.com/Mail/ComposeMessage.aspx?to=<?php echo esc_attr( $contact->email ); ?>&subject=<?php echo urlencode( $contact->subject ); ?>" target="_blank" class="classified_contact_email">AOL</a> /

	<a href="http://compose.mail.yahoo.com/?to=<?php echo esc_attr( $contact->email ); ?>&subject=<?php echo urlencode( $contact->subject ); ?>" target="_blank" class="classified_contact_email">Yahoo</a> /

	<a href="http://mail.live.com/mail/EditMessageLight.aspx?n=&to=<?php echo esc_attr( $contact->email ); ?>&subject=<?php echo urlencode( $contact->subject ); ?>" target="_blank" class="classified_contact_email">Outlook</a>

</p>