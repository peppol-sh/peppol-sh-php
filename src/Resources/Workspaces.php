<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Workspaces and their members.
 *
 * @phpstan-import-type ChangeWorkspaceMemberRoleParams from \PeppolSh\Generated\Types
 * @phpstan-import-type ChangeWorkspaceMemberRoleResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type CreateWorkspaceParams from \PeppolSh\Generated\Types
 * @phpstan-import-type CreateWorkspaceResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type DeleteWorkspaceResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetWorkspaceResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type InviteWorkspaceMemberParams from \PeppolSh\Generated\Types
 * @phpstan-import-type InviteWorkspaceMemberResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWorkspaceAuditEventsQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWorkspaceAuditEventsResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWorkspaceMembersResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWorkspacesResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type RemoveWorkspaceMemberResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type TransferWorkspaceOwnershipParams from \PeppolSh\Generated\Types
 * @phpstan-import-type TransferWorkspaceOwnershipResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type UpdateWorkspaceParams from \PeppolSh\Generated\Types
 * @phpstan-import-type UpdateWorkspaceResponse from \PeppolSh\Generated\Types
 */
class Workspaces extends AbstractResource
{
    /**
     * `POST /v1/workspaces`: creates a workspace.
     *
     * @param CreateWorkspaceParams $params
     *
     * @return CreateWorkspaceResponse
     *
     * @throws PeppolException
     */
    public function create(array $params): array
    {
        return $this->json('POST', '/v1/workspaces', [], $params);
    }

    /**
     * `GET /v1/workspaces`: the workspaces the account is a member of.
     *
     * @return ListWorkspacesResponse
     *
     * @throws PeppolException
     */
    public function list(): array
    {
        return $this->json('GET', '/v1/workspaces');
    }

    /**
     * `GET /v1/workspaces/{id}`: one workspace.
     *
     * @return GetWorkspaceResponse
     *
     * @throws PeppolException
     */
    public function get(string $id): array
    {
        return $this->json('GET', '/v1/workspaces/' . self::seg($id));
    }

    /**
     * `PATCH /v1/workspaces/{id}`: changes the given fields of a workspace.
     *
     * @param UpdateWorkspaceParams $params
     *
     * @return UpdateWorkspaceResponse
     *
     * @throws PeppolException
     */
    public function update(string $id, array $params): array
    {
        return $this->json('PATCH', '/v1/workspaces/' . self::seg($id), [], $params);
    }

    /**
     * `DELETE /v1/workspaces/{id}`: deletes a workspace.
     *
     * @return DeleteWorkspaceResponse
     *
     * @throws PeppolException
     */
    public function delete(string $id): array
    {
        return $this->json('DELETE', '/v1/workspaces/' . self::seg($id));
    }

    /**
     * `GET /v1/workspaces/{id}/members`: the members of a workspace.
     *
     * @return ListWorkspaceMembersResponse
     *
     * @throws PeppolException
     */
    public function listMembers(string $id): array
    {
        return $this->json('GET', '/v1/workspaces/' . self::seg($id) . '/members');
    }

    /**
     * `POST /v1/workspaces/{id}/members`: adds a member to a workspace.
     *
     * @param InviteWorkspaceMemberParams $params
     *
     * @return InviteWorkspaceMemberResponse
     *
     * @throws PeppolException
     */
    public function inviteMember(string $id, array $params): array
    {
        return $this->json('POST', '/v1/workspaces/' . self::seg($id) . '/members', [], $params);
    }

    /**
     * `PATCH /v1/workspaces/{id}/members/{accountId}`: changes the role of a member.
     *
     * @param ChangeWorkspaceMemberRoleParams $params
     *
     * @return ChangeWorkspaceMemberRoleResponse
     *
     * @throws PeppolException
     */
    public function changeMemberRole(string $id, string $accountId, array $params): array
    {
        return $this->json('PATCH', '/v1/workspaces/' . self::seg($id) . '/members/' . self::seg($accountId), [], $params);
    }

    /**
     * `DELETE /v1/workspaces/{id}/members/{accountId}`: removes a member from a workspace.
     *
     * @return RemoveWorkspaceMemberResponse
     *
     * @throws PeppolException
     */
    public function removeMember(string $id, string $accountId): array
    {
        return $this->json('DELETE', '/v1/workspaces/' . self::seg($id) . '/members/' . self::seg($accountId));
    }

    /**
     * `POST /v1/workspaces/{id}/transfer-ownership`: makes a different member the owner.
     *
     * @param TransferWorkspaceOwnershipParams $params
     *
     * @return TransferWorkspaceOwnershipResponse
     *
     * @throws PeppolException
     */
    public function transferOwnership(string $id, array $params): array
    {
        return $this->json('POST', '/v1/workspaces/' . self::seg($id) . '/transfer-ownership', [], $params);
    }

    /**
     * `GET /v1/workspaces/{id}/audit`: one page of the workspace audit trail. Cursor-paginated (`limit`, `cursor`).
     *
     * @param ListWorkspaceAuditEventsQuery $params
     *
     * @return ListWorkspaceAuditEventsResponse
     *
     * @throws PeppolException
     */
    public function listAuditEvents(string $id, array $params = []): array
    {
        return $this->json('GET', '/v1/workspaces/' . self::seg($id) . '/audit', $params);
    }
}
