<tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td><?php echo $totalcompleted;?></td>
    <td><?php echo $totalquotas;?></td>
    <td style="padding: 3px;">
        <?php if (App()->user->checkAccess('quotas', ['crud' => 'create', 'entity' => 'survey', 'entity_id' => $iSurveyId])) { ?>
            <?php echo CHtml::form(["admin/quotas",'sa' => 'newquota', 'surveyid' => $iSurveyId], 'post'); ?>
            <input name="submit" type="submit" class="quota_new" value="<?php eT("Add New ls\models\Quota");?>" />
            <input type="hidden" name="sid" value="<?php echo $iSurveyId;?>" />
            <input type="hidden" name="action" value="quotas" />
            <input type="hidden" name="subaction" value="new_quota" />
            </form>

            <?php } ?>                                
    </td>
    	</tr>
	</tbody>
</table>
