<h2><?php echo __('Detalles del pago', 'simplepay'); ?></h2>
<?php //print_r($response); die; ?>
<p>SimplePay ya ha notificado al comercio que has realizado el pago exitosamente. Nuestra plataforma estará monitoreando la red de Chauchas hasta que existan 6 confirmaciones de tu pago. En ese momento tu comprá será completamente aprobada. Si el pago falla, te avisaremos. </p>
<table class="shop_table order_details">
    <tbody>
        <tr>
            <th><?php echo __('Método de pago', 'simplepay'); ?></th>
            <td>SimplePay</td>
        </tr>
        <tr>
            <th><?php echo __('ID Transacción', 'simplepay'); ?></th>
            <td><?php echo $response['transaction']['uuid']; ?></td>
        </tr>
        <tr>
            <th><?php echo __('Fecha recepción pago', 'simplepay'); ?></th>
            <td><?php echo (new DateTime($response['transaction']['accepted_at']))->format('d-m-Y H:i:s'); ?></td>
        </tr>

        <tr>
            <th><?php echo __('Fecha aprobación', 'simplepay'); ?></th>
            <td><?php echo $response['transaction']['completed_at'] ? (new DateTime($response['transaction']['completed_at']))->format('d-m-Y H:i:s') : '-'; ?></td>
        </tr>
        <tr>
            <th><?php echo __('Monto', 'simplepay'); ?></th>
            <td><?php echo $response['transaction']['amount']; ?></td>
        </tr>
        <tr>
            <th><?php echo __('Moneda', 'simplepay'); ?></th>
            <td><?php echo $response['transaction']['currency']; ?></td>
        </tr>
        <tr>
            <th><?php echo __('Tipo de pago', 'simplepay'); ?></th>
            <td><?php echo $response['transaction']['payment_method']; ?></td>
        </tr>
        <?php if ($response['transaction']['payment_method'] == 'chauchas') { ?>
        <tr>
            <th><?php echo __('Estado', 'simplepay'); ?></th>
            <td>
                <?php
                if ($order->get_meta('simplepay_completed') === true) { ?>
                    <strong>Pago completo y aceptado</strong>
                <?php } else { ?>
                    Esperando confirmaciones de la red de Chauchas
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>